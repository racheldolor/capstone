<?php
session_start();
require_once '../config/database.php';

// Authentication check - allow head, central, admin, and director
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'central', 'admin', 'director'])) {
    header('Location: ../index.php');
    exit();
}

// Get student ID from query parameter
$student_id = $_GET['student_id'] ?? null;

if (!$student_id) {
    die('Student ID is required');
}

$pdo = getDBConnection();

try {
    // Get student data from student_artists table
    $stmt = $pdo->prepare("SELECT * FROM student_artists WHERE id = ?");
    $stmt->execute([$student_id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app) {
        die('Student profile not found');
    }

    // Fetch participation records from student_participation_records
    $stmt = $pdo->prepare("SELECT * FROM student_participation_records WHERE student_id = ? ORDER BY participation_date DESC");
    $stmt->execute([$app['id']]);
    $participations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch affiliation records from student_affiliation_records
    $stmt = $pdo->prepare("SELECT * FROM student_affiliation_records WHERE student_id = ? ORDER BY years_active DESC");
    $stmt->execute([$app['id']]);
    $affiliations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get cultural group name from the student profile
    $cultural_group = $app['cultural_group'] ?? $app['performance_type'] ?? 'Not specified';

} catch (Exception $e) {
    die('Error fetching profile data: ' . $e->getMessage());
}

// Generate PDF
require_once '../student/tcpdf/tcpdf.php';

class PerformerProfilePDF extends TCPDF {
    public function Header() {
        // Empty header - we'll add it manually
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Create PDF instance
$pdf = new PerformerProfilePDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('CFAD Student Artist System');
$pdf->SetAuthor($app['first_name'] . ' ' . $app['last_name']);
$pdf->SetTitle("Performer's Profile Form - " . $app['first_name'] . ' ' . $app['last_name']);
$pdf->SetSubject("Performer's Profile");

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);

// Add first page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Add timestamp at the top
$pdf->SetFont('helvetica', 'I', 9);
$pdf->SetTextColor(100, 100, 100);
date_default_timezone_set('Asia/Manila');
$currentDateTime = date('F d, Y - h:i A');
$pdf->Cell(0, 6, 'Generated: ' . $currentDateTime, 0, 1, 'R');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

// Header/Title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, "PERFORMER'S PROFILE FORM", 0, 1, 'C');
$pdf->Ln(5);

// Cultural Group Section
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'CULTURAL GROUP / TYPE OF PERFORMANCE', 0, 1, 'L', true);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 7, $cultural_group, 0, 1, 'L');
$pdf->Ln(3);

// Personal Information Section
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'PERSONAL INFORMATION', 0, 1, 'L', true);
$pdf->Ln(2);

// Save Y position for photo placement
$photoStartY = $pdf->GetY();

// Photo placeholder (1x1 passport size - 30x30mm on the right side)
if ($app['profile_photo'] && file_exists('../' . $app['profile_photo'])) {
    // Place photo at right side: page width - right margin - photo width
    // A4 width = 210mm, margins = 15mm each side, so usable width = 180mm
    // Photo position X = 15 (left margin) + 180 (usable width) - 30 (photo width) = 165
    $pdf->Image('../' . $app['profile_photo'], 165, $photoStartY, 30, 30, '', '', '', true, 150, '', false, false, 1, false, false, false);
}

$pdf->SetFont('helvetica', '', 9);

// Name row - limit width to not overlap with photo (max width 130mm to leave space for photo)
$pdf->Cell(43, 6, 'First Name: ' . ($app['first_name'] ?? ''), 0, 0, 'L');
$pdf->Cell(43, 6, 'Middle Name: ' . ($app['middle_name'] ?? ''), 0, 0, 'L');
$pdf->Cell(44, 6, 'Last Name: ' . ($app['last_name'] ?? ''), 0, 1, 'L');

// Addresses (limit to 130mm width)
$currentX = $pdf->GetX();
$pdf->MultiCell(130, 6, 'Permanent Address: ' . ($app['permanent_address'] ?? $app['address'] ?? ''), 0, 'L');
$pdf->SetX($currentX);
$pdf->MultiCell(130, 6, 'Present Address: ' . ($app['present_address'] ?? ''), 0, 'L');

// DOB, Age, Gender row (limit to 130mm width)
$pdf->Cell(55, 6, 'Date of Birth: ' . ($app['date_of_birth'] ?? ''), 0, 0, 'L');
$pdf->Cell(30, 6, 'Age: ' . ($app['age'] ?? ''), 0, 0, 'L');
$pdf->Cell(45, 6, 'Gender: ' . ($app['gender'] ?? ''), 0, 1, 'L');

// Place of Birth (limit to 130mm width)
$pdf->MultiCell(130, 6, 'Place of Birth: ' . ($app['place_of_birth'] ?? ''), 0, 'L');

// After text, check if we need to move Y position past the photo
$textEndY = $pdf->GetY();
$photoEndY = $photoStartY + 30; // photo height is 30mm
if ($textEndY < $photoEndY) {
    $pdf->SetY($photoEndY);
}

// Contact Information (now full width is available)
$pdf->Cell(90, 6, 'Email Address: ' . ($app['email'] ?? ''), 0, 0, 'L');
$pdf->Cell(90, 6, 'Contact Number: ' . ($app['contact_number'] ?? ''), 0, 1, 'L');

$pdf->Ln(3);

// Family Background Section
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'FAMILY BACKGROUND', 0, 1, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Ln(2);

$pdf->Cell(0, 6, "Father's Name: " . ($app['father_name'] ?? ''), 0, 1, 'L');
$pdf->Cell(0, 6, "Mother's Name: " . ($app['mother_name'] ?? ''), 0, 1, 'L');
$pdf->Cell(90, 6, 'Guardian: ' . ($app['guardian'] ?? 'N/A'), 0, 0, 'L');
$pdf->Cell(90, 6, 'Guardian Contact: ' . ($app['guardian_contact'] ?? 'N/A'), 0, 1, 'L');

$pdf->Ln(3);

// Academic Information Section
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'ACADEMIC INFORMATION', 0, 1, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Ln(2);

$pdf->Cell(90, 6, 'Campus: ' . ($app['campus'] ?? ''), 0, 0, 'L');
$pdf->Cell(90, 6, 'College: ' . ($app['college'] ?? ''), 0, 1, 'L');

$pdf->Cell(90, 6, 'SR-Code: ' . ($app['sr_code'] ?? ''), 0, 0, 'L');
$pdf->Cell(90, 6, 'Year Level: ' . ($app['year_level'] ?? ''), 0, 1, 'L');

$pdf->Cell(0, 6, 'Program/Course: ' . ($app['program'] ?? ''), 0, 1, 'L');

$pdf->Cell(90, 6, '1st Semester Units: ' . ($app['first_semester_units'] ?? '0'), 0, 0, 'L');
$pdf->Cell(90, 6, '2nd Semester Units: ' . ($app['second_semester_units'] ?? '0'), 0, 1, 'L');

$pdf->MultiCell(0, 6, 'Instructors: ' . ($app['instructors'] ?? 'Not specified'), 0, 'L');

$pdf->Ln(3);

// Check if we need a new page before adding tables
if ($pdf->GetY() > 220) {
    $pdf->AddPage();
}

// Participation Section
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'PARTICIPATION IN ARTS-RELATED ACTIVITIES (Last Five Years)', 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(25, 7, 'DATE', 1, 0, 'C', true);
$pdf->Cell(60, 7, 'TITLE/NATURE', 1, 0, 'C', true);
$pdf->Cell(50, 7, 'LEVEL', 1, 0, 'C', true);
$pdf->Cell(45, 7, 'RANK/AWARD', 1, 1, 'C', true);

$pdf->SetFont('helvetica', '', 7);
if (!empty($participations)) {
    foreach ($participations as $participation) {
        // Check if we need a new page
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
            // Repeat header
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell(25, 7, 'DATE', 1, 0, 'C', true);
            $pdf->Cell(60, 7, 'TITLE/NATURE', 1, 0, 'C', true);
            $pdf->Cell(50, 7, 'LEVEL', 1, 0, 'C', true);
            $pdf->Cell(45, 7, 'RANK/AWARD', 1, 1, 'C', true);
            $pdf->SetFont('helvetica', '', 7);
        }
        
        $pdf->Cell(25, 6, $participation['participation_date'] ?? '', 1, 0, 'L');
        $pdf->Cell(60, 6, $participation['event_name'] ?? '', 1, 0, 'L');
        $pdf->Cell(50, 6, $participation['participation_level'] ?? '', 1, 0, 'L');
        $pdf->Cell(45, 6, $participation['rank_award'] ?? '', 1, 1, 'L');
    }
} else {
    $pdf->SetFillColor(250, 250, 250);
    $pdf->Cell(180, 7, 'No participation records', 1, 1, 'C', true);
}

$pdf->Ln(3);

// Check if we need a new page before affiliation table
if ($pdf->GetY() > 220) {
    $pdf->AddPage();
}

// Affiliation Section
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'AFFILIATION/MEMBERSHIP IN ARTS ORGANIZATIONS', 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(50, 7, 'POSITION', 1, 0, 'C', true);
$pdf->Cell(100, 7, 'NAME OF ORGANIZATION', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'YEAR', 1, 1, 'C', true);

$pdf->SetFont('helvetica', '', 8);
if (!empty($affiliations)) {
    foreach ($affiliations as $affiliation) {
        // Check if we need a new page
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
            // Repeat header
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell(50, 7, 'POSITION', 1, 0, 'C', true);
            $pdf->Cell(100, 7, 'NAME OF ORGANIZATION', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'YEAR', 1, 1, 'C', true);
            $pdf->SetFont('helvetica', '', 8);
        }
        
        $pdf->Cell(50, 6, $affiliation['position'] ?? '', 1, 0, 'L');
        $pdf->Cell(100, 6, $affiliation['organization'] ?? '', 1, 0, 'L');
        $pdf->Cell(30, 6, $affiliation['years_active'] ?? '', 1, 1, 'C');
    }
} else {
    $pdf->SetFillColor(250, 250, 250);
    $pdf->Cell(180, 7, 'No affiliation records', 1, 1, 'C', true);
}

// Output PDF
$filename = 'Performer_Profile_' . str_replace(' ', '_', $app['first_name'] . '_' . $app['last_name']) . '.pdf';
$pdf->Output($filename, 'D'); // 'D' forces download
exit();
?>
