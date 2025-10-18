<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<?php
include("db_connect.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Log form submission for debugging
    error_log("Form submitted - POST data: " . print_r($_POST, true));
    
    try {
        // Sanitize and collect form data
        // performance_type may be sent as an array (performance_type[]) so handle both
        if (isset($_POST["performance_type"])) {
            $performance_type = is_array($_POST["performance_type"]) ? implode(',', $_POST["performance_type"]) : $_POST["performance_type"];
        } else {
            $performance_type = '';
        }
        $consent = $_POST["consent"] ?? '';
        $first_name = $_POST["first_name"] ?? '';
        $middle_name = $_POST["middle_name"] ?? '';
        $last_name = $_POST["last_name"] ?? '';
        $full_name = trim($first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name);
        $address = $_POST["address"] ?? '';
        $present_address = $_POST["present_address"] ?? '';
        $date_of_birth = $_POST["date_of_birth"] ?? '';
        $age = $_POST["age"] ?? '';
        $gender = $_POST["gender"] ?? '';
        $place_of_birth = $_POST["place_of_birth"] ?? '';
        $email = $_POST["email"] ?? '';
        $contact_number = $_POST["contact_number"] ?? '';
        $father_name = $_POST["father_name"] ?? '';
        $mother_name = $_POST["mother_name"] ?? '';
        $guardian = $_POST["guardian"] ?? '';
        $guardian_contact = $_POST["guardian_contact"] ?? '';
        $campus = $_POST["campus"] ?? '';
        $college = $_POST["college"] ?? '';
        $sr_code = $_POST["sr_code"] ?? '';
        $year_level = $_POST["year_level"] ?? '';
        $program = $_POST["program"] ?? '';
        $first_semester_units = (int)($_POST["first_semester_units"] ?? 0);
        $second_semester_units = (int)($_POST["second_semester_units"] ?? 0);
        $certification = isset($_POST["certification"]) ? 1 : 0;
        $signature_date = $_POST["signature_date"] ?? '';

        // Handle file uploads
        $profile_photo_path = null;
        $signature_image_path = null;
        
        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Handle profile photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photo_file = $_FILES['photo'];
            $photo_ext = strtolower(pathinfo($photo_file['name'], PATHINFO_EXTENSION));
            $allowed_photo_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($photo_ext, $allowed_photo_types)) {
                $photo_filename = 'profile_' . uniqid() . '.' . $photo_ext;
                $photo_path = $upload_dir . $photo_filename;
                
                if (move_uploaded_file($photo_file['tmp_name'], $photo_path)) {
                    $profile_photo_path = 'uploads/' . $photo_filename;
                    error_log("Profile photo uploaded: " . $profile_photo_path);
                } else {
                    error_log("Failed to move uploaded photo file");
                }
            }
        }
        
        // Handle signature image from canvas
        if (isset($_POST['signature_data']) && !empty($_POST['signature_data'])) {
            $signature_data = $_POST['signature_data'];
            // Remove data:image/png;base64, prefix if present
            $signature_data = preg_replace('#^data:image/[^;]+;base64,#', '', $signature_data);
            $signature_binary = base64_decode($signature_data);
            
            if ($signature_binary !== false && strlen($signature_binary) > 0) {
                $signature_filename = 'signature_' . uniqid() . '.png';
                $signature_path = $upload_dir . $signature_filename;
                
                if (file_put_contents($signature_path, $signature_binary)) {
                    $signature_image_path = 'uploads/' . $signature_filename;
                    error_log("Signature image saved: " . $signature_image_path);
                } else {
                    error_log("Failed to save signature image");
                }
            } else {
                error_log("Invalid signature data received");
            }
        }

        // Log the collected data
        error_log("Performance type captured: " . $performance_type);
        error_log("First semester units: " . $first_semester_units);
        error_log("Second semester units: " . $second_semester_units);

    // Insert data into database
    $stmt = $conn->prepare("
        INSERT INTO applications (
            performance_type, consent, first_name, middle_name, last_name, full_name, address, present_address, 
            date_of_birth, age, gender, place_of_birth, email, contact_number, father_name, mother_name, 
            guardian, guardian_contact, campus, college, sr_code, year_level, program, first_semester_units, 
            second_semester_units, profile_photo, signature_image, certification, signature_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Parameter types: 23 strings, 2 integers, 2 strings, 1 integer, 1 string = 29 total
    // s(23) + i + i + s + s + i + s
    $types = 'sssssssssssssssssssssss' . 'ii' . 'ss' . 'i' . 's';

    $stmt->bind_param(
        $types,
        $performance_type, $consent, $first_name, $middle_name, $last_name, $full_name, $address, $present_address, 
        $date_of_birth, $age, $gender, $place_of_birth, $email, $contact_number, $father_name, $mother_name, 
        $guardian, $guardian_contact, $campus, $college, $sr_code, $year_level, $program, 
        $first_semester_units, $second_semester_units, $profile_photo_path, $signature_image_path,
        $certification, $signature_date
    );

    $response = ['success' => false, 'message' => 'Unknown error'];

    try {
        if ($stmt->execute()) {
            $application_id = $conn->insert_id;
            error_log("Application inserted successfully with ID: " . $application_id);
            
            // Insert participation records if provided
            if (isset($_POST['participation_date']) && is_array($_POST['participation_date'])) {
                $participation_stmt = $conn->prepare("
                    INSERT INTO application_participation (application_id, participation_date, event_name, participation_level, rank_award) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                for ($i = 0; $i < count($_POST['participation_date']); $i++) {
                    $part_date = $_POST['participation_date'][$i] ?? '';
                    $part_event = $_POST['participation_event'][$i] ?? '';
                    $part_level = $_POST['participation_level'][$i] ?? '';
                    $part_rank = $_POST['participation_rank'][$i] ?? '';
                    
                    // Only insert if at least one field has data
                    if (!empty($part_date) || !empty($part_event) || !empty($part_level) || !empty($part_rank)) {
                        $participation_stmt->bind_param("issss", $application_id, $part_date, $part_event, $part_level, $part_rank);
                        if (!$participation_stmt->execute()) {
                            error_log("Error inserting participation record: " . $participation_stmt->error);
                        }
                    }
                }
                $participation_stmt->close();
            }
            
            // Insert affiliation records if provided
            if (isset($_POST['affiliation_position']) && is_array($_POST['affiliation_position'])) {
                $affiliation_stmt = $conn->prepare("
                    INSERT INTO application_affiliations (application_id, position, organization, years_active) 
                    VALUES (?, ?, ?, ?)
                ");
                
                for ($i = 0; $i < count($_POST['affiliation_position']); $i++) {
                    $aff_position = $_POST['affiliation_position'][$i] ?? '';
                    $aff_org = $_POST['affiliation_organization'][$i] ?? '';
                    $aff_years = $_POST['affiliation_years'][$i] ?? '';
                    
                    // Only insert if at least one field has data
                    if (!empty($aff_position) || !empty($aff_org) || !empty($aff_years)) {
                        $affiliation_stmt->bind_param("isss", $application_id, $aff_position, $aff_org, $aff_years);
                        if (!$affiliation_stmt->execute()) {
                            error_log("Error inserting affiliation record: " . $affiliation_stmt->error);
                        }
                    }
                }
                $affiliation_stmt->close();
            }
            
            $response = ['success' => true, 'message' => 'Application submitted successfully', 'application_id' => $application_id];
        } else {
            $error_msg = $stmt->error;
            error_log("Database error: " . $error_msg);
            $response = ['success' => false, 'message' => 'Database error: ' . $error_msg];
        }
    } catch (Exception $e) {
        error_log("Exception during form processing: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'An error occurred while processing your application. Please try again.'];
    }

    $stmt->close();

    // If request is AJAX (fetch), return JSON; otherwise, fall back to inline JS
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $acceptsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

    // Modern fetch API doesn't always set X-Requested-With, so also check for JSON accept header
    if ($isAjax || $acceptsJson) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if ($response['success']) {
        echo "<script>alert('Application submitted successfully! Your application ID is: " . ($response['application_id'] ?? 'N/A') . "\\n\\nIMPORTANT: Please use your SR Code to check your application status later.'); window.location.href='performer-profile-form.php';</script>";
    } else {
        echo "<script>alert('Error: " . addslashes($response['message']) . "');</script>";
    }
    
    } catch (Exception $e) {
        error_log("Form processing error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'An error occurred while processing your application: ' . $e->getMessage()];
        
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $acceptsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

        if ($isAjax || $acceptsJson) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } else {
            echo "<script>alert('Error: " . addslashes($response['message']) . "');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performer's Profile Form - Culture and Arts - BatStateU TNEU</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            padding: 2rem 1rem;
            line-height: 1.4;
        }

        .performer-profile-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .performer-profile-card {
            background: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        /* Header Section */
        .form-header {
            background: #fff;
            padding: 1.5rem 2rem;
            border-bottom: 2px solid #333;
        }

        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            border: 1px solid #333;
            padding: 1rem;
        }

        .logo-section {
            display: flex;
            align-items: center;
        }

        .logo {
            width: 60px;
            height: 60px;
            margin-right: 1rem;
        }

        .reference-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.9rem;
        }

        .ref-item {
            display: flex;
            gap: 0.5rem;
        }

        .ref-item .label {
            font-weight: 600;
            min-width: 100px;
        }

        .form-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        /* Form Body */
        .form-body {
            padding: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
            border: 1px solid #ddd;
            padding: 1.5rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .instruction {
            font-weight: 400;
            font-style: italic;
            color: #666;
            font-size: 0.9rem;
        }

        /* Checkbox Grid */
        .checkbox-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .checkbox-item.full-width {
            grid-column: 1 / -1;
        }

        .checkbox-item input[type="checkbox"] {
            display: none;
        }

        .checkmark {
            width: 16px;
            height: 16px;
            border: 2px solid #333;
            background: #fff;
            position: relative;
            flex-shrink: 0;
        }

        .checkbox-item input[type="checkbox"]:checked + .checkmark::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
            font-weight: bold;
            color: #333;
        }

        .inline-text {
            flex: 1;
            padding: 0.25rem 0.5rem;
            border: none;
            border-bottom: 1px solid #333;
            background: transparent;
            font-size: 0.85rem;
            margin-left: 0.5rem;
        }

        /* Privacy Note */
        .privacy-note {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
            font-size: 0.8rem;
            line-height: 1.5;
            text-align: justify;
        }

        /* Consent Section */
        .consent-section h4 {
            font-size: 1rem;
            margin-bottom: 0.75rem;
            color: #333;
        }

        .consent-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .radio-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .radio-item input[type="radio"] {
            display: none;
        }

        .radiomark {
            width: 16px;
            height: 16px;
            border: 2px solid #333;
            border-radius: 50%;
            background: #fff;
            position: relative;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .radio-item input[type="radio"]:checked + .radiomark::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            background: #333;
            border-radius: 50%;
        }

        .consent-text {
            font-size: 0.8rem;
            line-height: 1.4;
            color: #555;
            margin-left: 0.5rem;
        }

        /* Photo Section */
        .photo-section {
            float: right;
            margin-left: 1rem;
            margin-bottom: 1rem;
            text-align: center;
        }

        .photo-placeholder {
            width: 120px;
            height: 120px;
            border: 2px solid #333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: #666;
            text-align: center;
            background: #f8f9fa;
            margin-bottom: 0.5rem;
            overflow: hidden;
        }

        .photo-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .upload-photo-btn {
            background: #ff5a5a;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: background 0.3s ease;
            width: 120px;
        }

        .upload-photo-btn:hover {
            background: #ff3333;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
            color: #333;
        }

        .form-group input,
        .form-group select {
            padding: 0.5rem;
            border: 1px solid #333;
            border-radius: 4px;
            font-size: 0.9rem;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #ff5a5a;
            box-shadow: 0 0 0 2px rgba(255, 90, 90, 0.2);
        }

        .form-row {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .form-group.half {
            flex: 1;
        }

        .form-group.quarter {
            flex: 0.5;
        }

        .checkbox-row {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .checkbox-inline {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .checkbox-inline input[type="checkbox"] {
            width: 16px;
            height: 16px;
        }

        .inline-units {
            width: 80px;
            padding: 0.25rem 0.5rem;
            border: 1px solid #333;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-left: 0.5rem;
            margin-right: 1rem;
        }

        /* Table Sections */
        .table-section {
            margin-top: 1rem;
        }

        .participation-table,
        .affiliation-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .participation-table th,
        .affiliation-table th,
        .participation-table td,
        .affiliation-table td {
            border: 1px solid #333;
            padding: 0.75rem 0.5rem;
            text-align: left;
        }

        .participation-table th,
        .affiliation-table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .sub-text {
            font-weight: 400;
            font-style: italic;
            font-size: 0.75rem;
        }

        .participation-table input,
        .participation-table select,
        .affiliation-table input {
            width: 100%;
            border: none;
            padding: 0.25rem;
            font-size: 0.85rem;
            background: transparent;
        }

        .participation-table input:focus,
        .participation-table select:focus,
        .affiliation-table input:focus {
            outline: 1px solid #ff5a5a;
            background: #fff;
        }

        .add-row-btn {
            background: #ff5a5a;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .add-row-btn:hover {
            background: #ff3333;
        }

        /* Certification */
        .certification {
            margin: 1.5rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 4px;
        }

        /* Signature Section */
        .signature-section {
            display: flex;
            justify-content: space-between;
            align-items: end;
            margin: 2rem 0;
            gap: 2rem;
        }

        .signature-field {
            flex: 2;
        }

        .signature-field label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .signature-canvas {
            border: 2px solid #333;
            background: #fff !important;
            cursor: crosshair;
            border-radius: 4px;
            width: 400px;
            height: 150px;
            display: block;
        }

        .signature-controls {
            margin-top: 0.5rem;
        }

        .clear-signature-btn {
            background: #666;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .clear-signature-btn:hover {
            background: #555;
        }

        .date-field {
            flex: 1;
            padding-bottom: 19%;
        }

        .date-field label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .date-field input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #333;
            border-radius: 4px;
        }

        /* Attachment Note */
        .attachment-note {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 4px;
            border-left: 4px solid #ffc107;
            margin: 1rem 0;
        }

        .attachment-note p {
            font-size: 0.9rem;
            color: #856404;
            margin: 0;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
            flex-wrap: wrap;
        }

        .cancel-btn {
            background: transparent;
            color: #666;
            border: 2px solid #ddd;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .cancel-btn:hover {
            background: #f8f9fa;
            border-color: #ccc;
        }

        .submit-btn {
            background: linear-gradient(135deg, #ff5a5a, #ff7a6b);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 90, 90, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 90, 90, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        /* Secondary Actions */
        .secondary-actions {
            display: flex;
            justify-content: center;
            margin-top: 1rem;
        }

        .status-btn {
            background: transparent;
            color: #666;
            border: 2px solid #ddd;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .status-btn:hover {
            background: #f8f9fa;
            border-color: #ccc;
        }

        /* Success Message */
        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .performer-profile-container {
                padding: 1rem 0.5rem;
            }
            
            .form-header {
                padding: 1rem;
            }
            
            .header-info {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-body {
                padding: 1rem;
            }
            
            .checkbox-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-group.half,
            .form-group.quarter {
                flex: 1;
            }
            
            .signature-section {
                flex-direction: column;
                gap: 1rem;
            }
            
            .photo-section {
                float: none;
                margin: 0 auto 1rem auto;
                text-align: center;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .secondary-actions {
                margin-top: 0.5rem;
            }
            
            .participation-table,
            .affiliation-table {
                font-size: 0.8rem;
            }
            
            .participation-table th,
            .affiliation-table th,
            .participation-table td,
            .affiliation-table td {
                padding: 0.5rem 0.25rem;
            }
        }

        @media (max-width: 480px) {
            .form-title {
                font-size: 1.2rem;
            }
            
            .section-title {
                font-size: 1rem;
            }
            
            .reference-info {
                font-size: 0.8rem;
            }
            
            .logo {
                width: 50px;
                height: 50px;
            }
        }

        /* Status Modal */
        .status-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s ease;
        }

        .modal-close:hover {
            background: #f0f0f0;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .status-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            margin: 1rem 0;
            transition: border-color 0.3s ease;
        }

        .status-input:focus {
            outline: none;
            border-color: #ff5a5a;
        }

        .check-status-btn {
            background: #ff5a5a;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            width: 100%;
            transition: background 0.3s ease;
        }

        .check-status-btn:hover {
            background: #ff3333;
        }

        .status-result {
            margin-top: 1rem;
        }

        .status-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            border-left: 4px solid #ff5a5a;
        }

        .status-card h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }

        .status-card p {
            margin: 0 0 0.5rem 0;
            color: #666;
        }

        .status-card small {
            color: #999;
        }

        .loading {
            color: #007bff;
            font-style: italic;
        }

        .error {
            color: #ff5a5a;
            font-weight: 500;
        }

        /* Remove Row Button */
        .remove-row-btn {
            background: #ff5a5a;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
            margin: 0 auto;
        }

        .remove-row-btn:hover {
            background: #ff3333;
        }

        .remove-row-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Center action column content */
        .participation-table td:last-child,
        .affiliation-table td:last-child,
        .participation-table th:last-child,
        .affiliation-table th:last-child {
            text-align: center;
            vertical-align: middle;
            width: 60px;
            padding: 0.5rem;
        }

        /* Center the dash in first rows */
        .participation-table tbody tr:first-child td:last-child,
        .affiliation-table tbody tr:first-child td:last-child {
            text-align: center;
            font-weight: bold;
            color: #666;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="performer-profile-container">
        <div class="performer-profile-card">
            <div class="form-header">
                <h1 class="form-title">PERFORMER'S PROFILE FORM</h1>
            </div>

            <div class="form-body">
                <form class="performer-form" id="performerForm" method="POST" action="performer-profile-form.php" enctype="multipart/form-data">
                    <!-- Type of Performance Section -->
                    <div class="form-section">
                        <h3 class="section-title">Type of Performance: <span class="instruction">(Include the name of Cultural Group you are interested in joining.)</span></h3>
                        <div class="checkbox-grid">
                            <label class="checkbox-item">
                                <input type="checkbox" name="performance_type[]" value="performing_arts">
                                <span class="checkmark"></span>
                                Performing Arts
                                <input type="text" id="performanceOther" class="inline-text" placeholder="Specify">
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="performance_type[]" value="music">
                                <span class="checkmark"></span>
                                Music
                                <input type="text" class="inline-text" placeholder="Specify">
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="performance_type[]" value="dance">
                                <span class="checkmark"></span>
                                Dance
                                <input type="text" class="inline-text" placeholder="Specify">
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="performance_type[]" value="theater">
                                <span class="checkmark"></span>
                                Theater
                                <input type="text" class="inline-text" placeholder="Specify">
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="performance_type[]" value="visual_arts">
                                <span class="checkmark"></span>
                                Visual Arts
                                <input type="text" class="inline-text" placeholder="Specify">
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="performance_type[]" value="literary_arts">
                                <span class="checkmark"></span>
                                Literary Arts
                                <input type="text" class="inline-text" placeholder="Specify">
                            </label>
                        </div>
                        <div class="privacy-note">
                            <p><strong>Pursuant to Republic Act No. 10173</strong> also known as the Data Privacy Act of 2012, the Batangas State University, the National Engineering University, recognizes the fundamental human right of privacy, of communication while ensuring the protection of personal data and/or stakeholders and ensure that all the information collected from them are all processed in accordance with the principles of transparency, legitimate purpose and proportionality mandated under the said Data Privacy Act.</p>
                        </div>
                        <div class="consent-section">
                            <h4>Consent of Data Subject</h4>
                            <div class="consent-options">
                                <label class="radio-item">
                                    <input type="radio" name="consent" value="yes" required>
                                    <span class="radiomark"></span>
                                    Yes
                                    <span class="consent-text">I hereby give my consent and hereby authorize Batangas State University, the National Engineering University to share, disclose, or transfer any personal information I have provided herein to any third party or affiliate as they may deem necessary for any legal purposes in compliance with the Data Privacy.</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="consent" value="no" required>
                                    <span class="radiomark"></span>
                                    No
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">I. PERSONAL INFORMATION</h3>
                        <div class="photo-section">
                            <div class="photo-placeholder" id="photoPreview">
                                <span>2" x 2" ID Picture</span>
                            </div>
                            <input type="file" id="photoUpload" name="photo" accept="image/*" style="display: none;">
                            <button type="button" class="upload-photo-btn" onclick="document.getElementById('photoUpload').click()">
                                Upload Photo
                            </button>
                        </div>
                        <div class="form-grid">
                            <div class="form-row">
                                <div class="form-group half">
                                    <label>First Name: <span style="color: red;">*</span></label>
                                    <input type="text" name="first_name" required>
                                </div>
                                <div class="form-group half">
                                    <label>Middle Name:</label>
                                    <input type="text" name="middle_name">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Last Name: <span style="color: red;">*</span></label>
                                <input type="text" name="last_name" required>
                            </div>
                            <div class="form-group">
                                <label>Address:</label>
                                <input type="text" name="address" required>
                            </div>
                            <div class="form-group">
                                <label>Present Address:</label>
                                <input type="text" name="present_address">
                            </div>
                            <div class="form-row">
                                <div class="form-group half">
                                    <label>Date of Birth:</label>
                                    <input type="date" id="birthdate" name="date_of_birth" required>
                                </div>
                                <div class="form-group quarter">
                                    <label>Age:</label>
                                    <input type="number" id="age" name="age" min="1" max="100">
                                </div>
                                <div class="form-group quarter">
                                    <label>Gender:</label>
                                    <select name="gender" required>
                                        <option value="">Select</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Place of Birth:</label>
                                <input type="text" name="place_of_birth">
                            </div>
                            <div class="form-group">
                                <label>Email:</label>
                                    <input type="email" id="emailAddress" name="email" required>
                            </div>
                            <div class="form-group">
                                <label>Contact Number:</label>
                                    <input type="tel" id="contactNumber" name="contact_number">
                            </div>
                            <div class="form-group">
                                <label>Father's Name:</label>
                                <input type="text" name="father_name">
                            </div>
                            <div class="form-group">
                                <label>Mother's Name:</label>
                                <input type="text" name="mother_name">
                            </div>
                            <div class="form-group">
                                <label>Guardian (if not living with Parents):</label>
                                <input type="text" name="guardian">
                            </div>
                            <div class="form-group">
                                <label>Contact Number (Parent/Guardian):</label>
                                <input type="tel" name="guardian_contact">
                            </div>
                        </div>
                    </div>

                    <!-- Educational Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">II. EDUCATIONAL INFORMATION</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Campus:</label>
                                <input type="text" name="campus">
                            </div>
                            <div class="form-row">
                                <div class="form-group half">
                                    <label>College:</label>
                                    <input type="text" name="college">
                                </div>
                                <div class="form-group quarter">
                                    <label>SR Code:</label>
                                    <input type="text" name="sr_code">
                                </div>
                                <div class="form-group quarter">
                                    <label>Year Level:</label>
                                    <select name="year_level">
                                        <option value="">Select</option>
                                        <option value="1st">1st Year</option>
                                        <option value="2nd">2nd Year</option>
                                        <option value="3rd">3rd Year</option>
                                        <option value="4th">4th Year</option>
                                        <option value="5th">5th Year</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group half">
                                    <label>Program:</label>
                                    <input type="text" name="program">
                                </div>
                                <div class="form-group half">
                                    <label>Number of Units:</label>
                                    <div class="checkbox-row">
                                        <label class="checkbox-inline">
                                            <input type="checkbox" name="semester" value="first">
                                            First Semester
                                        </label>
                                        <input type="number" class="inline-units" name="first_semester_units" placeholder="Units" min="0" max="30">
                                        <label class="checkbox-inline">
                                            <input type="checkbox" name="semester" value="second">
                                            Second Semester
                                        </label>
                                        <input type="number" class="inline-units" name="second_semester_units" placeholder="Units" min="0" max="30">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Participation Section -->
                    <div class="form-section">
                        <h3 class="section-title">III. PARTICIPATION IN THE FIELD OF INTEREST / ACHIEVEMENTS</h3>
                        <div class="table-section">
                            <table class="participation-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Event</th>
                                        <th>Level<br><span class="sub-text">(Local, Regional, National, International)</span></th>
                                        <th>Rank (Place)</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="participationTableBody">
                                    <tr>
                                        <td><input type="date" name="participation_date[]"></td>
                                        <td><input type="text" name="participation_event[]"></td>
                                        <td>
                                            <select name="participation_level[]">
                                                <option value="">Select</option>
                                                <option value="local">Local</option>
                                                <option value="regional">Regional</option>
                                                <option value="national">National</option>
                                                <option value="international">International</option>
                                            </select>
                                        </td>
                                        <td><input type="text" name="participation_rank[]"></td>
                                        <td>-</td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="add-row-btn" onclick="addParticipationRow()">Add Row</button>
                        </div>
                    </div>

                    <!-- Affiliation Section -->
                    <div class="form-section">
                        <h3 class="section-title">IV. AFFILIATION TO ORGANIZATIONS</h3>
                        <div class="table-section">
                            <table class="affiliation-table">
                                <thead>
                                    <tr>
                                        <th>Position</th>
                                        <th>Name of Organization</th>
                                        <th>Inclusive Years</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="affiliationTableBody">
                                    <tr>
                                        <td><input type="text" name="affiliation_position[]"></td>
                                        <td><input type="text" name="affiliation_organization[]"></td>
                                        <td><input type="text" name="affiliation_years[]" placeholder="e.g., 2020-2023"></td>
                                        <td>-</td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="add-row-btn" onclick="addAffiliationRow()">Add Row</button>
                        </div>
                    </div>

                    <!-- Certification Section -->
                    <div class="form-section">
                        <div class="certification">
                            <label class="checkbox-item full-width">
                                <input type="checkbox" id="certificationCheck" name="certification" required>
                                <span class="checkmark"></span>
                                I hereby certify that all information in this form is true and correct. Any misrepresentation of facts will render this invalid and immediately disqualifies my membership to the cultural group.
                            </label>
                        </div>
                        
                        <div class="signature-section">
                            <div class="signature-field">
                                <label>Signature of Performer/Member</label>
                                <canvas id="signatureCanvas" class="signature-canvas" width="400" height="150"></canvas>
                                <div class="signature-controls">
                                    <button type="button" class="clear-signature-btn" onclick="clearSignature()">Clear Signature</button>
                                </div>
                            </div>
                            <div class="date-field">
                                <label>Date:</label>
                                <input type="date" name="signature_date">
                            </div>
                        </div>

                        <div class="attachment-note">
                            <p><strong>*Required Attachments:</strong> Certified True Copy of Certificates and Recognitions</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="cancel-btn">Cancel</button>
                        <button type="submit" class="submit-btn">Submit Form</button>
                    </div>
                    
                    <div class="secondary-actions">
                        <button type="button" class="status-btn" onclick="viewApplicationStatus()">View Application Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        class PerformerProfileForm {
            constructor() {
                console.log('PerformerProfileForm constructor called');
                
                try {
                    this.form = document.getElementById('performerForm');
                    console.log('Form element found:', this.form);
                    
                    this.participationTable = document.querySelector('.participation-table tbody');
                    this.affiliationTable = document.querySelector('.affiliation-table tbody');
                    this.submitBtn = document.querySelector('.submit-btn');
                    this.cancelBtn = document.querySelector('.cancel-btn');
                    this.isSubmitting = false;
                    
                    // Signature canvas setup
                    this.canvas = document.getElementById('signatureCanvas');
                    console.log('Canvas element found:', this.canvas);
                    
                    this.ctx = this.canvas ? this.canvas.getContext('2d') : null;
                    console.log('Canvas context:', this.ctx);
                    
                    this.isDrawing = false;
                    this.hasSignature = false;
                    
                    console.log('About to call initializeForm...');
                    this.initializeForm();
                    console.log('PerformerProfileForm constructor completed successfully');
                    
                } catch (error) {
                    console.error('Error in PerformerProfileForm constructor:', error);
                    throw error;
                }
            }

            initializeForm() {
                console.log('initializeForm() called');
                
                try {
                    console.log('Calling bindEvents...');
                    this.bindEvents();
                    
                    console.log('initializeForm() completed successfully');
                } catch (error) {
                    console.error('Error in initializeForm:', error);
                    throw error;
                }
            }

            bindEvents() {
                // Form submission
                this.form.addEventListener('submit', (e) => this.handleSubmit(e));
                
                // Cancel button
                this.cancelBtn.addEventListener('click', (e) => this.handleCancel(e));
                
                // Dynamic row addition - these buttons use onclick attributes in HTML, so we don't need to add listeners here
                
                // Performance type validation
                this.setupPerformanceTypeValidation();
                
                // Photo section validation
                this.setupPhotoSection();
                
                // Real-time validation for required fields
                this.setupRealTimeValidation();
                
                // Signature canvas setup
                this.setupSignatureCanvas();
            }

            setupPerformanceTypeValidation() {
                const performanceCheckboxes = document.querySelectorAll('input[name="performance_type[]"]');
                const otherInput = document.getElementById('performanceOther');
                
                performanceCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', () => {
                        this.validatePerformanceTypes();
                        
                        // Show/hide other input field
                        if (checkbox.value === 'others' && checkbox.checked) {
                            otherInput.style.display = 'inline';
                            otherInput.required = true;
                        } else if (checkbox.value === 'others' && !checkbox.checked) {
                            otherInput.style.display = 'none';
                            otherInput.required = false;
                            otherInput.value = '';
                        }
                    });
                });
            }

            validatePerformanceTypes() {
                const performanceCheckboxes = document.querySelectorAll('input[name="performance_type[]"]');
                const checkedBoxes = document.querySelectorAll('input[name="performance_type[]"]:checked');
                const errorDiv = document.getElementById('performanceTypeError');
                
                if (checkedBoxes.length === 0) {
                    this.showFieldError(performanceCheckboxes[0], 'Please select at least one type of performance.');
                    return false;
                } else {
                    this.clearFieldError(performanceCheckboxes[0]);
                    return true;
                }
            }

            setupPhotoSection() {
                const photoUpload = document.getElementById('photoUpload');
                const photoPlaceholder = document.getElementById('photoPreview');
                
                if (photoUpload && photoPlaceholder) {
                    photoUpload.addEventListener('change', (e) => {
                        const file = e.target.files[0];
                        
                        if (file) {
                            // Check if file is an image
                            if (!file.type.startsWith('image/')) {
                                this.showAlert('Please select a valid image file.', 'error');
                                photoUpload.value = '';
                                return;
                            }
                            
                            // Check file size (15MB = 15 * 1024 * 1024 bytes)
                            const maxSize = 15 * 1024 * 1024; // 15MB in bytes
                            if (file.size > maxSize) {
                                this.showAlert('Image file size must be less than 15MB.', 'error');
                                photoUpload.value = '';
                                return;
                            }
                            
                            // Show preview
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                photoPlaceholder.innerHTML = `<img src="${e.target.result}" alt="Profile Photo">`;
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }
            }

            setupRealTimeValidation() {
                const requiredFields = this.form.querySelectorAll('input[required], select[required]');
                
                requiredFields.forEach(field => {
                    field.addEventListener('blur', () => this.validateField(field));
                    field.addEventListener('input', () => this.clearFieldError(field));
                });
                
                // Email validation
                const emailField = document.getElementById('emailAddress');
                if (emailField) {
                    emailField.addEventListener('blur', () => this.validateEmail(emailField));
                }
                
                // Contact number validation
                const contactField = document.getElementById('contactNumber');
                if (contactField) {
                    contactField.addEventListener('input', () => this.formatContactNumber(contactField));
                }
                
                // Age calculation
                const birthdateField = document.getElementById('birthdate');
                const ageField = document.getElementById('age');
                if (birthdateField && ageField) {
                    birthdateField.addEventListener('change', () => {
                        const age = this.calculateAge(birthdateField.value);
                        ageField.value = age;
                    });
                }
            }

            setupSignatureCanvas() {
                console.log('Setting up signature canvas...'); // Debug log
                
                if (!this.canvas) {
                    console.error('Canvas element not found!');
                    return;
                }
                
                if (!this.ctx) {
                    console.error('Canvas context not available!');
                    return;
                }
                
                // Set canvas size properly
                this.canvas.width = 400;
                this.canvas.height = 150;
                
                // Set canvas background
                this.ctx.fillStyle = '#ffffff';
                this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
                
                // Set drawing properties
                this.ctx.strokeStyle = '#000000';
                this.ctx.lineWidth = 3;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.ctx.globalCompositeOperation = 'source-over';
                
                console.log('Canvas initialized:', this.canvas.width, 'x', this.canvas.height);
                
                // Mouse events
                this.canvas.addEventListener('mousedown', (e) => {
                    console.log('Mouse down event triggered');
                    this.startDrawing(e);
                });
                this.canvas.addEventListener('mousemove', (e) => {
                    if (this.isDrawing) console.log('Mouse move while drawing');
                    this.draw(e);
                });
                this.canvas.addEventListener('mouseup', () => {
                    console.log('Mouse up event triggered');
                    this.stopDrawing();
                });
                this.canvas.addEventListener('mouseout', () => {
                    console.log('Mouse out event triggered');
                    this.stopDrawing();
                });
                
                console.log('Mouse event listeners attached');
                
                // Touch events for mobile
                this.canvas.addEventListener('touchstart', (e) => {
                    e.preventDefault();
                    const touch = e.touches[0];
                    const mouseEvent = new MouseEvent('mousedown', {
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                    this.canvas.dispatchEvent(mouseEvent);
                });
                
                this.canvas.addEventListener('touchmove', (e) => {
                    e.preventDefault();
                    const touch = e.touches[0];
                    const mouseEvent = new MouseEvent('mousemove', {
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                    this.canvas.dispatchEvent(mouseEvent);
                });
                
                this.canvas.addEventListener('touchend', (e) => {
                    e.preventDefault();
                    const mouseEvent = new MouseEvent('mouseup', {});
                    this.canvas.dispatchEvent(mouseEvent);
                });
            }

            startDrawing(e) {
                this.isDrawing = true;
                const rect = this.canvas.getBoundingClientRect();
                const scaleX = this.canvas.width / rect.width;
                const scaleY = this.canvas.height / rect.height;
                const x = (e.clientX - rect.left) * scaleX;
                const y = (e.clientY - rect.top) * scaleY;
                
                // Set drawing properties
                this.ctx.strokeStyle = '#000000';
                this.ctx.lineWidth = 3;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                
                // Start new path and set starting point
                this.ctx.beginPath();
                this.ctx.moveTo(x, y);
                
                this.hasSignature = true;
                console.log('Started drawing at:', x, y); // Debug log
            }

            draw(e) {
                if (!this.isDrawing) return;
                
                const rect = this.canvas.getBoundingClientRect();
                const scaleX = this.canvas.width / rect.width;
                const scaleY = this.canvas.height / rect.height;
                const x = (e.clientX - rect.left) * scaleX;
                const y = (e.clientY - rect.top) * scaleY;
                
                // Draw line to new position
                this.ctx.lineTo(x, y);
                this.ctx.stroke();
                
                // Begin new path for next segment to avoid redrawing entire path
                this.ctx.beginPath();
                this.ctx.moveTo(x, y);
                
                console.log('Drawing to:', x, y); // Debug log
            }

            stopDrawing() {
                if (this.isDrawing) {
                    this.isDrawing = false;
                    this.ctx.beginPath(); // End current path
                    console.log('Stopped drawing'); // Debug log
                }
            }

            clearSignature() {
                console.log('clearSignature() called'); // Debug log
                
                if (!this.canvas) {
                    console.error('Canvas not found in clearSignature');
                    return;
                }
                
                if (!this.ctx) {
                    console.error('Canvas context not found in clearSignature');
                    return;
                }
                
                console.log('Clearing signature canvas...'); // Debug log
                
                // Clear the canvas
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                
                // Set white background
                this.ctx.fillStyle = '#ffffff';
                this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
                
                this.hasSignature = false;
                
                // Reset drawing properties after clearing
                this.ctx.strokeStyle = '#000000';
                this.ctx.lineWidth = 3;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                
                console.log('Signature cleared successfully'); // Debug log
            }

            getSignatureData() {
                if (!this.hasSignature) return null;
                return this.canvas.toDataURL();
            }

            addParticipationRow() {
                const tbody = this.participationTable;
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><input type="date" name="participation_date[]"></td>
                    <td><input type="text" name="participation_event[]"></td>
                    <td>
                        <select name="participation_level[]">
                            <option value="">Select</option>
                            <option value="local">Local</option>
                            <option value="regional">Regional</option>
                            <option value="national">National</option>
                            <option value="international">International</option>
                        </select>
                    </td>
                    <td><input type="text" name="participation_rank[]"></td>
                    <td><button type="button" class="remove-row-btn" onclick="removeParticipationRow(this)" title="Remove row">−</button></td>
                `;
                
                tbody.appendChild(row);
            }

            addAffiliationRow() {
                const tbody = this.affiliationTable;
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><input type="text" name="affiliation_position[]"></td>
                    <td><input type="text" name="affiliation_organization[]"></td>
                    <td><input type="text" name="affiliation_years[]" placeholder="e.g., 2020-2023"></td>
                    <td><button type="button" class="remove-row-btn" onclick="removeAffiliationRow(this)" title="Remove row"> −</button></td>
                `;
                
                tbody.appendChild(row);
            }

            calculateAge(birthdate) {
                if (!birthdate) return '';
                
                const birth = new Date(birthdate);
                const today = new Date();
                let age = today.getFullYear() - birth.getFullYear();
                const monthDiff = today.getMonth() - birth.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                    age--;
                }
                
                return age;
            }

            formatContactNumber(field) {
                let value = field.value.replace(/\D/g, ''); // Remove non-digits
                
                // Limit to 11 digits for Philippine mobile numbers
                if (value.length > 11) {
                    value = value.slice(0, 11);
                }
                
                // Format as Philippine mobile number
                if (value.length >= 4) {
                    value = value.replace(/(\d{4})(\d{0,3})(\d{0,4})/, (match, p1, p2, p3) => {
                        let formatted = p1;
                        if (p2) formatted += '-' + p2;
                        if (p3) formatted += '-' + p3;
                        return formatted;
                    });
                }
                
                field.value = value;
            }

            validateField(field) {
                const value = field.value.trim();
                
                if (field.hasAttribute('required') && !value) {
                    this.showFieldError(field, `${this.getFieldLabel(field)} is required.`);
                    return false;
                }
                
                this.clearFieldError(field);
                return true;
            }

            validateEmail(field) {
                const email = field.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && !emailRegex.test(email)) {
                    this.showFieldError(field, 'Please enter a valid email address.');
                    return false;
                }
                
                this.clearFieldError(field);
                return true;
            }

            validateConsent() {
                const consentRadios = document.querySelectorAll('input[name="consent"]');
                const checkedConsent = document.querySelector('input[name="consent"]:checked');
                
                if (!checkedConsent) {
                    this.showFieldError(consentRadios[0], 'Please select your consent for data privacy.');
                    return false;
                }
                
                if (checkedConsent.value === 'no') {
                    this.showAlert('You must consent to data processing to submit this form.', 'error');
                    return false;
                }
                
                this.clearFieldError(consentRadios[0]);
                return true;
            }

            validateCertification() {
                const certificationCheck = document.getElementById('certificationCheck');
                
                if (!certificationCheck.checked) {
                    this.showFieldError(certificationCheck, 'Please certify that all information provided is true and correct.');
                    return false;
                }
                
                this.clearFieldError(certificationCheck);
                return true;
            }

            validateForm() {
                console.log('Starting form validation...'); // Debug log
                let isValid = true;
                
                // Validate performance types
                console.log('Validating performance types...'); // Debug log
                if (!this.validatePerformanceTypes()) {
                    console.log('Performance types validation failed'); // Debug log
                    isValid = false;
                }
                
                // Validate required fields
                console.log('Validating required fields...'); // Debug log
                const requiredFields = this.form.querySelectorAll('input[required], select[required]');
                console.log('Found required fields:', requiredFields.length); // Debug log
                requiredFields.forEach(field => {
                    if (!this.validateField(field)) {
                        console.log('Required field validation failed:', field.name || field.id); // Debug log
                        isValid = false;
                    }
                });
                
                // Validate email
                console.log('Validating email...'); // Debug log
                const emailField = document.getElementById('emailAddress');
                if (emailField && emailField.value) {
                    if (!this.validateEmail(emailField)) {
                        console.log('Email validation failed'); // Debug log
                        isValid = false;
                    }
                }
                
                // Validate consent
                console.log('Validating consent...'); // Debug log
                if (!this.validateConsent()) {
                    console.log('Consent validation failed'); // Debug log
                    isValid = false;
                }
                
                // Validate certification
                console.log('Validating certification...'); // Debug log
                if (!this.validateCertification()) {
                    console.log('Certification validation failed'); // Debug log
                    isValid = false;
                }
                
                console.log('Form validation result:', isValid); // Debug log
                return isValid;
            }

            showFieldError(field, message) {
                this.clearFieldError(field);
                
                const errorDiv = document.createElement('div');
                errorDiv.className = 'field-error';
                errorDiv.style.cssText = `
                    color: #ff5a5a;
                    font-size: 0.8rem;
                    margin-top: 0.25rem;
                    display: block;
                `;
                errorDiv.textContent = message;
                
                // Find the best place to insert the error
                const parent = field.closest('.form-group') || field.closest('.checkbox-item') || field.parentNode;
                parent.appendChild(errorDiv);
                
                // Add error styling to field
                field.style.borderColor = '#ff5a5a';
            }

            clearFieldError(field) {
                const parent = field.closest('.form-group') || field.closest('.checkbox-item') || field.parentNode;
                const existingError = parent.querySelector('.field-error');
                
                if (existingError) {
                    existingError.remove();
                }
                
                // Reset field styling
                field.style.borderColor = '';
            }

            getFieldLabel(field) {
                const label = field.closest('.form-group')?.querySelector('label');
                if (label) {
                    return label.textContent.replace('*', '').trim();
                }
                
                return field.name || 'This field';
            }

            collectFormData() {
                // Build FormData directly so file u2ploads and arrays are preserved
                const formData = new FormData(this.form);

                // Handle performance_type[] with specifications - REMOVE the default entries first
                formData.delete('performance_type[]');
                
                // Now manually add performance types with specifications
                const performanceCheckboxes = document.querySelectorAll('input[name="performance_type[]"]:checked');
                performanceCheckboxes.forEach(checkbox => {
                    let value = checkbox.value;
                    
                    // Find the specification input field that's a sibling in the same checkbox-item
                    const checkboxItem = checkbox.closest('.checkbox-item');
                    const specifyInput = checkboxItem.querySelector('.inline-text');
                    
                    if (specifyInput && specifyInput.value.trim()) {
                        value += ': ' + specifyInput.value.trim();
                    }
                    
                    // Append to FormData
                    formData.append('performance_type[]', value);
                });

                // Rest of the function remains the same...
                // Append participation rows
                const participationRows = this.participationTable.querySelectorAll('tr');
                participationRows.forEach((row, idx) => {
                    const date = row.querySelector('input[name="participation_date[]"]')?.value || '';
                    const event = row.querySelector('input[name="participation_event[]"]')?.value || '';
                    const level = row.querySelector('select[name="participation_level[]"]')?.value || '';
                    const rank = row.querySelector('input[name="participation_rank[]"]')?.value || '';

                    formData.append('participation_date[]', date);
                    formData.append('participation_event[]', event);
                    formData.append('participation_level[]', level);
                    formData.append('participation_rank[]', rank);
                });

                // Append affiliation rows
                const affiliationRows = this.affiliationTable.querySelectorAll('tr');
                affiliationRows.forEach((row, idx) => {
                    const position = row.querySelector('input[name="affiliation_position[]"]')?.value || '';
                    const org = row.querySelector('input[name="affiliation_organization[]"]')?.value || '';
                    const years = row.querySelector('input[name="affiliation_years[]"]')?.value || '';

                    formData.append('affiliation_position[]', position);
                    formData.append('affiliation_organization[]', org);
                    formData.append('affiliation_years[]', years);
                });

                // Append signature image if present
                const sigDataUrl = this.getSignatureData();
                if (sigDataUrl) {
                    // Pass the base64 data directly to PHP
                    formData.append('signature_data', sigDataUrl);
                }

                return formData;
            }
            async handleSubmit(e) {
                console.log('Form submit event triggered'); // Debug log
                e.preventDefault();
                
                console.log('isSubmitting:', this.isSubmitting); // Debug log
                if (this.isSubmitting) return;
                
                // Clear any previous success messages
                const existingSuccess = document.querySelector('.success-message');
                if (existingSuccess) {
                    existingSuccess.remove();
                }
                
                console.log('About to validate form...'); // Debug log
                // Validate form
                if (!this.validateForm()) {
                    console.log('Form validation failed'); // Debug log
                    this.showAlert('Please correct the errors above before submitting.', 'error');
                    return;
                }
                
                console.log('Form validation passed, starting submission...'); // Debug log
                
                this.isSubmitting = true;
                this.submitBtn.disabled = true;
                this.submitBtn.textContent = 'Submitting...';
                
                try {
                    let formData = this.collectFormData();

                    // POST to backend
                    // Post to the same PHP handler in the current directory
                    const resp = await fetch('performer-profile-form.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            // Let browser set Content-Type for FormData
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    console.log('Response status:', resp.status);
                    console.log('Response headers:', resp.headers);
                    
                    // Get the raw response text first
                    const responseText = await resp.text();
                    console.log('Raw response:', responseText);
                    
                    // Try to parse as JSON
                    let result = null;
                    try {
                        result = JSON.parse(responseText);
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        console.error('Response was not valid JSON:', responseText.substring(0, 500));
                        this.showAlert('Server returned invalid response format. Please check the console for details.', 'error');
                        return;
                    }
                    
                    console.log('Parsed result:', result); // Debug log

                    if (resp.ok && result && result.success) {
                        this.showSuccessMessage();
                        // Don't automatically reset the form - let users decide if they want to submit another application
                        // setTimeout(() => this.resetForm(), 2000);
                    } else {
                        const msg = (result && result.message) ? result.message : `Server error (${resp.status}): ${resp.statusText}`;
                        console.error('Server error details:', msg); // Debug log
                        this.showAlert(msg, 'error');
                    }

                } catch (error) {
                    console.error('Submission error:', error);
                    this.showAlert('An error occurred while submitting the form. Please try again.', 'error');
                } finally {
                    this.isSubmitting = false;
                    this.submitBtn.disabled = false;
                    this.submitBtn.textContent = 'Submit Application';
                }
            }

            showSuccessMessage() {
                const successDiv = document.createElement('div');
                successDiv.className = 'success-message';
                successDiv.innerHTML = `
                    <strong>Application Submitted Successfully!</strong><br>
                    Your performer profile has been submitted for review. You will be contacted within 3-5 business days.<br><br>
                    <strong>Important:</strong> Please save your SR Code to check your application status later.<br>
                    <a href="check-status.php" style="color: #ff5a5a; text-decoration: underline;">Click here to check your application status</a>
                `;
                
                this.form.insertBefore(successDiv, this.form.firstChild);
                
                // Scroll to top to show success message
                successDiv.scrollIntoView({ behavior: 'smooth' });
            }

            showAlert(message, type = 'info') {
                // Remove existing alerts
                const existingAlert = document.querySelector('.form-alert');
                if (existingAlert) {
                    existingAlert.remove();
                }
                
                const alertDiv = document.createElement('div');
                alertDiv.className = 'form-alert';
                alertDiv.style.cssText = `
                    padding: 1rem;
                    margin-bottom: 1rem;
                    border-radius: 4px;
                    font-weight: 500;
                    ${type === 'error' 
                        ? 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;' 
                        : 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;'
                    }
                `;
                alertDiv.textContent = message;
                
                this.form.insertBefore(alertDiv, this.form.firstChild);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
                
                // Scroll to alert
                alertDiv.scrollIntoView({ behavior: 'smooth' });
            }

            resetForm() {
                this.form.reset();
                
                // Reset dynamic tables to original single row
                this.participationTable.innerHTML = `
                    <tr>
                        <td><input type="date" name="participation_date[]"></td>
                        <td><input type="text" name="participation_event[]"></td>
                        <td>
                            <select name="participation_level[]">
                                <option value="">Select</option>
                                <option value="local">Local</option>
                                <option value="regional">Regional</option>
                                <option value="national">National</option>
                                <option value="international">International</option>
                            </select>
                        </td>
                        <td><input type="text" name="participation_rank[]"></td>
                        <td>-</td>
                    </tr>
                `;
                
                this.affiliationTable.innerHTML = `
                    <tr>
                        <td><input type="text" name="affiliation_position[]"></td>
                        <td><input type="text" name="affiliation_organization[]"></td>
                        <td><input type="text" name="affiliation_years[]" placeholder="e.g., 2020-2023"></td>
                        <td>-</td>
                    </tr>
                `;
                
                // Reset photo
                const photoPlaceholder = document.getElementById('photoPreview');
                if (photoPlaceholder) {
                    photoPlaceholder.innerHTML = '<span>2" x 2" ID Picture</span>';
                }
                
                // Reset photo upload input
                const photoUpload = document.getElementById('photoUpload');
                if (photoUpload) {
                    photoUpload.value = '';
                }
                
                // Clear all errors
                document.querySelectorAll('.field-error').forEach(error => error.remove());
                document.querySelectorAll('input, select').forEach(field => {
                    field.style.borderColor = '';
                });
                
                // Reset other input visibility
                const otherInput = document.getElementById('performanceOther');
                if (otherInput) {
                    otherInput.style.display = 'none';
                    otherInput.required = false;
                }
            }

            handleCancel(e) {
                e.preventDefault();
                
                if (confirm('Are you sure you want to cancel? All entered data will be lost.')) {
                    // Redirect to dashboard or previous page
                    window.location.href = '../index.php';
                }
                // If user clicks "Cancel" in the dialog, do nothing (stay on the form)
            }
        }

        // Global functions for HTML onclick events
        function addParticipationRow() {
            if (window.performerForm && window.performerForm.addParticipationRow) {
                window.performerForm.addParticipationRow();
            }
        }

        function addAffiliationRow() {
            if (window.performerForm && window.performerForm.addAffiliationRow) {
                window.performerForm.addAffiliationRow();
            }
        }

        function removeParticipationRow(button) {
            const row = button.closest('tr');
            const tbody = row.parentNode;
            
            // Don't allow removal if this is the only row or the first row
            if (tbody.children.length <= 1 || row === tbody.firstElementChild) {
                return;
            }
            
            row.remove();
        }

        function removeAffiliationRow(button) {
            const row = button.closest('tr');
            const tbody = row.parentNode;
            
            // Don't allow removal if this is the only row or the first row
            if (tbody.children.length <= 1 || row === tbody.firstElementChild) {
                return;
            }
            
            row.remove();
        }

        function clearSignature() {
            console.log('Global clearSignature() called'); // Debug log
            
            if (!window.performerForm) {
                console.error('performerForm not found on window object');
                return;
            }
            
            console.log('performerForm object:', window.performerForm);
            console.log('Available methods:', Object.getOwnPropertyNames(Object.getPrototypeOf(window.performerForm)));
            
            if (!window.performerForm.clearSignature) {
                console.error('clearSignature method not found on performerForm');
                // Try to call it directly
                if (window.performerForm.canvas && window.performerForm.ctx) {
                    console.log('Trying to clear canvas directly...');
                    const canvas = window.performerForm.canvas;
                    const ctx = window.performerForm.ctx;
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    console.log('Canvas cleared directly');
                }
                return;
            }
            
            window.performerForm.clearSignature();
        }

        function viewApplicationStatus() {
            // Redirect to the dedicated status checking page
            window.location.href = 'check-status.php';
        }

        // Initialize the form when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            console.log('DOM loaded, initializing PerformerProfileForm...');
            try {
                window.performerForm = new PerformerProfileForm();
                console.log('PerformerProfileForm initialized successfully');
                console.log('performerForm methods:', Object.getOwnPropertyNames(Object.getPrototypeOf(window.performerForm)));
            } catch (error) {
                console.error('Error initializing PerformerProfileForm:', error);
            }
        });

        // Add styles for dynamic elements
        const style = document.createElement('style');
        style.textContent = `
            .remove-row-btn {
                background: #ff5a5a;
                color: white;
                border: none;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.3s ease;
            }
            
            .remove-row-btn:hover {
                background: #ff3333;
            }
            
            .field-error {
                color: #ff5a5a !important;
                font-size: 0.8rem !important;
                margin-top: 0.25rem !important;
                display: block !important;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>