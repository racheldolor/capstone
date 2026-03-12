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
    $stmt = $pdo->prepare("SELECT participation_date as date, event_name, participation_level as level, rank_award FROM student_participation_records WHERE student_id = ? ORDER BY participation_date DESC");
    $stmt->execute([$app['id']]);
    $participations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch competition records from student_competition_records
    $stmt = $pdo->prepare("SELECT competition_date as date, event_name, competition_level as level, rank_award FROM student_competition_records WHERE student_id = ? ORDER BY competition_date DESC");
    $stmt->execute([$app['id']]);
    $competitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merge participation and competition records (same structure)
    $allParticipations = array_merge($participations, $competitions);
    // Sort by date descending
    usort($allParticipations, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    // Fetch affiliation records from student_affiliation_records
    $stmt = $pdo->prepare("SELECT position, organization, years_active FROM student_affiliation_records WHERE student_id = ? ORDER BY created_at DESC");
    $stmt->execute([$app['id']]);
    $affiliations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get performance type / cultural group
    $performance_type = $app['performance_type'] ?? $app['cultural_group'] ?? '';

} catch (Exception $e) {
    die('Error fetching profile data: ' . $e->getMessage());
}

// Generate PDF using TCPDF
require_once '../student/tcpdf/tcpdf.php';

class PerformerProfilePDF extends TCPDF {
    public function Header() {
        // Empty - we'll create custom header
    }
    
    public function Footer() {
        // Empty footer for cleaner look
    }
}

// Create PDF instance with LONG PAPER size (8.5 x 13 inches = 215.9 x 330.2 mm)
$pdf = new PerformerProfilePDF('P', 'mm', array(215.9, 330.2), true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('BatStateU Culture & Arts Management System');
$pdf->SetAuthor($app['first_name'] . ' ' . $app['last_name']);
$pdf->SetTitle("Performer's Profile Form - " . $app['sr_code']);
$pdf->SetSubject("Performer's Profile Form");

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins (5mm on all sides for equal margins)
$pdf->SetMargins(5.6, 5.6, 5.6);
$pdf->SetAutoPageBreak(false);

// Add page
$pdf->AddPage();

// ========================================
// HEADER SECTION WITH LOGO AND INFO
// ========================================
$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(5.6, 5.6);

// Define cell dimensions to match official template exactly
$headerHeight = 12;
$logoCell = 30;
$refCell = 60;
$effCell = 70;
$revCell = 44.4;

// Draw header table cells
$pdf->Cell($logoCell, $headerHeight, '', 1, 0, 'C');
$pdf->Cell($refCell, $headerHeight, 'Reference No.: BatStateU-FO-OCA-03', 1, 0, 'L');
$pdf->Cell($effCell, $headerHeight, 'Effectivity Date: August 01, 2023', 1, 0, 'L');
$pdf->Cell($revCell, $headerHeight, 'Revision No.: 01', 1, 1, 'L');

// Place logo INSIDE the first cell
$logoPaths = [
    ['path' => '../assets/logo.jpg', 'type' => 'JPEG'],
    ['path' => '../assets/OCA Logo.png', 'type' => 'PNG'],
    ['path' => '../assets/bsu.png', 'type' => 'PNG'],
    ['path' => '../assets/bsu.jpg', 'type' => 'JPEG']
];

foreach ($logoPaths as $logoInfo) {
    if (file_exists($logoInfo['path'])) {
        try {
            $logoSize = 10;
            $logoX = 5.6 + ($logoCell - $logoSize) / 2;
            $logoY = 5.6 + ($headerHeight - $logoSize) / 2;
            $pdf->Image($logoInfo['path'], $logoX, $logoY, $logoSize, $logoSize, $logoInfo['type']);
            break;
        } catch (Exception $e) {
            continue;
        }
    }
}

// Title centered below header with border
$pdf->SetFont('helvetica', 'B', 13);
$pdf->Cell(0, 8, "PERFORMER'S PROFILE FORM", 1, 1, 'C');

$pdf->Ln(1);

// ========================================
// TYPE OF PERFORMANCE SECTION
// ========================================
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$currentY = $pdf->GetY();
$pdf->Cell(42, 5.5, 'Type of Performance: ', 'LTB', 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(162.8, 5.5, '(Include the name of Cultural Group you are interested in joining:)', 'TRB', 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$performance_types = ['Performing Arts', 'Music', 'Dance', 'Theater', 'Visual Arts', 'Literary Arts'];

foreach ($performance_types as $index => $type) {
    $checked = false;
    if (!empty($performance_type)) {
        $checked = stripos($performance_type, $type) !== false;
    }
    
    $y = $pdf->GetY();
    $pdf->Rect(6.6, $y + 1, 3, 3);
    if ($checked) {
        $pdf->Line(7, $y + 2.2, 8, $y + 3.2);
        $pdf->Line(8, $y + 3.2, 9.6, $y + 1.2);
    }
    
    $pdf->Cell(5, 5, '', 'L', 0, 'L');
    $pdf->Cell(30, 5, ' ' . $type, 0, 0, 'L');
    $fillText = $checked ? ': ' . $performance_type : ': ____________________________________________________________';
    $borderType = ($index == count($performance_types) - 1) ? 'LBR' : 'LR';
    $pdf->Cell(169.4, 5, $fillText, $borderType, 1, 'L');
}

// Data Privacy Act Notice
$pdf->SetFont('helvetica', '', 6.5);
$privacyText = 'Pursuant to Republic Act No. 10173, also known as the Data Privacy Act of 2012, the Batangas State University, the National Engineering University, recognizes its commitment to protect and respect the privacy of its customers and/or stakeholders and ensure that all information collected from them are all processed in accordance with the principles of transparency, legitimate purpose and proportionality mandated under the Data Privacy Act of 2012.';
$pdf->MultiCell(0, 3, $privacyText, 'LTR', 'L');

// Consent of Data Subject
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(0, 5.5, 'Consent of Data Subject', 1, 1, 'C', true);

// Draw Yes/No checkboxes
$y = $pdf->GetY();
$pdf->Rect(6.6, $y + 0.8, 3, 3);
$pdf->Line(7, $y + 2, 8, $y + 3);
$pdf->Line(8, $y + 3, 9.6, $y + 1);
$pdf->Rect(17, $y + 0.8, 3, 3);

$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell(5, 4.5, '', 'L', 0, 'L');
$pdf->Cell(5, 4.5, 'Yes', 0, 0, 'L');
$pdf->Cell(5, 4.5, '', 0, 0, 'L');
$pdf->Cell(5, 4.5, 'No', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 7);
$consentText = 'I hereby give my consent and hereby authorize Batangas State University, the National Engineering University to share, disclose, or transfer my personal data with a third party in the pursuit of journalistic, artistic, literary, research or any legal purposes in compliance with the Data Privacy Act of 2012.';
$pdf->MultiCell(184.4, 3, $consentText, 'R', 'J');

$pdf->SetFont('helvetica', 'I', 7);
$pdf->Cell(0, 4, '(Please fill out the form and submit to the respective trainer. Put N/A if not applicable.)', 'LBR', 1, 'C');

$pdf->Ln(2);

// ========================================
// I. PERSONAL INFORMATION
// ========================================
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(0, 5.5, 'I. PERSONAL INFORMATION', 1, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$photoY = $pdf->GetY();

// Row 1: Name with photo cell (56.8mm height extends to Contact Number row)
$nameData = trim(($app['first_name'] ?? '') . ' ' . ($app['middle_name'] ?? '') . ' ' . ($app['last_name'] ?? ''));
$pdf->Cell(30, 6, 'Name:', 1, 0, 'L');
$pdf->Cell(120.4, 6, $nameData, 1, 0, 'L');
$pdf->Cell(54, 56.8, '', 1, 1, 'C');

// Row 2: Address
$pdf->SetXY(5.6, $photoY + 6);
$pdf->Cell(30, 6, 'Address:', 1, 0, 'L');
$pdf->Cell(120.4, 6, $app['address'] ?? $app['permanent_address'] ?? '', 1, 0, 'L');

// Row 3: Present Address
$pdf->SetXY(5.6, $photoY + 12);
$pdf->Cell(30, 6, 'Present Address:', 1, 0, 'L');
$pdf->Cell(120.4, 6, $app['present_address'] ?? '', 1, 0, 'L');

// Row 4: Date of Birth, Age, Gender
$pdf->SetXY(5.6, $photoY + 18);
$pdf->Cell(30, 6, 'Date of Birth:', 1, 0, 'L');
$pdf->Cell(50, 6, $app['date_of_birth'] ?? '', 1, 0, 'L');
$pdf->Cell(10, 6, 'Age:', 1, 0, 'L');
$pdf->Cell(12, 6, $app['age'] ?? '', 1, 0, 'C');
$pdf->Cell(15, 6, 'Gender:', 1, 0, 'L');
$pdf->Cell(33.4, 6, $app['gender'] ?? '', 1, 0, 'L');

// Row 5: Place of Birth
$pdf->SetXY(5.6, $photoY + 24);
$pdf->Cell(30, 6, 'Place of Birth:', 1, 0, 'L');
$pdf->Cell(120.4, 6, $app['place_of_birth'] ?? '', 1, 0, 'L');

// Row 6: Email and Contact
$pdf->SetXY(5.6, $photoY + 30);
$pdf->Cell(30, 6, 'Email Address:', 1, 0, 'L');
$pdf->Cell(60, 6, $app['email'] ?? '', 1, 0, 'L');
$pdf->Cell(30, 6, 'Contact Number:', 1, 0, 'L');
$pdf->Cell(30.4, 6, $app['contact_number'] ?? '', 1, 0, 'L');

// Row 7: Father's Name
$pdf->SetXY(5.6, $photoY + 36);
$pdf->Cell(30, 6, "Father's Name:", 1, 0, 'L');
$pdf->Cell(120.4, 6, $app['father_name'] ?? '', 1, 0, 'L');

// Photo image in photo cell
$photoX = 156;
if ($app['profile_photo'] && file_exists('../' . $app['profile_photo'])) {
    try {
        $photoExt = strtolower(pathinfo($app['profile_photo'], PATHINFO_EXTENSION));
        $imageType = '';
        if (in_array($photoExt, ['jpg', 'jpeg'])) {
            $imageType = 'JPEG';
        } elseif ($photoExt === 'png') {
            $imageType = 'PNG';
        } elseif ($photoExt === 'gif') {
            $imageType = 'GIF';
        }
        $pdf->Image('../' . $app['profile_photo'], $photoX + 1.6, $photoY + 1.6, 50.8, 50.8, $imageType);
    } catch (Exception $e) {
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetXY($photoX, $photoY + 23);
        $pdf->Cell(54, 5, '(Attach 2x2 picture here)', 0, 0, 'C');
    }
} else {
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetXY($photoX, $photoY + 23);
    $pdf->Cell(54, 5, '(Attach 2x2 picture here)', 0, 0, 'C');
}

// Row 8: Mother's Name (still within photo cell height)
$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(5.6, $photoY + 42);
$pdf->Cell(30, 4.4, "Mother's Name:", 1, 0, 'L');
$pdf->Cell(120.4, 4.4, $app['mother_name'] ?? '', 1, 0, 'L');

// Row 9: Guardian (still within photo cell height)
$pdf->SetXY(5.6, $photoY + 46.4);
$pdf->Cell(50, 4.4, 'Guardian (if not living with Parents):', 1, 0, 'L');
$pdf->Cell(100.4, 4.4, ($app['guardian'] && $app['guardian'] != 'N/A') ? $app['guardian'] : '', 1, 0, 'L');

// Row 10: Contact Number (still within photo cell height)
$pdf->SetXY(5.6, $photoY + 50.8);
$pdf->Cell(50, 6, 'Contact Number (Parent/Guardian):', 1, 0, 'L');
$pdf->Cell(100.4, 6, $app['guardian_contact'] ?? $app['emergency_contact_number'] ?? '', 1, 1, 'L');

$pdf->Ln(2);

// ========================================
// II. EDUCATIONAL INFORMATION
// ========================================
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(0, 5.5, 'II. EDUCATIONAL INFORMATION', 1, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);

// Campus, SR Code, Year Level
$pdf->Cell(18, 6, 'Campus:', 1, 0, 'L');
$pdf->Cell(82, 6, $app['campus'] ?? '', 1, 0, 'L');
$pdf->Cell(20, 6, 'SR Code:', 1, 0, 'L');
$pdf->Cell(35, 6, $app['sr_code'] ?? '', 1, 0, 'L');
$pdf->Cell(20, 6, 'Year Level:', 1, 0, 'L');
$pdf->Cell(29.4, 6, $app['year_level'] ?? '', 1, 1, 'C');

// College and Program  
$pdf->Cell(18, 6, 'College:', 1, 0, 'L');
$pdf->Cell(82, 6, $app['college'] ?? '', 1, 0, 'L');
$pdf->Cell(20, 6, 'Program:', 1, 0, 'L');
$pdf->Cell(84.4, 6, $app['program'] ?? '', 1, 1, 'L');

// Number of Units
$y = $pdf->GetY();
$pdf->Cell(30, 6, 'Number of Units:', 1, 0, 'L');

// Draw first checkbox and semester text together
$pdf->Rect(37.5, $y + 1.5, 3, 3);
$pdf->Cell(87, 6, '      First Semester ___________________', 1, 0, 'L');

// Draw second checkbox and semester text together
$pdf->Rect(124.5, $y + 1.5, 3, 3);
$pdf->Cell(87.4, 6, '      Second Semester ___________________', 1, 1, 'L');

$pdf->Ln(2);

// ========================================
// III. PARTICIPATION IN THE FIELD OF INTEREST / ACHIEVEMENTS
// ========================================
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(0, 5.5, 'III. PARTICIPATION IN THE FIELD OF INTEREST / ACHIEVEMENTS', 1, 1, 'L', true);

// Table headers
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(30, 5.5, 'Date', 1, 0, 'C', true);
$pdf->Cell(75, 5.5, 'Event', 1, 0, 'C', true);
$pdf->Cell(65, 5.5, 'Level', 1, 0, 'C', true);
$pdf->Cell(34.4, 5.5, 'Rank (Place)', 1, 1, 'C', true);

$pdf->SetFont('helvetica', '', 7);
$pdf->Cell(30, 3.5, '', 1, 0, 'C');
$pdf->Cell(75, 3.5, '', 1, 0, 'C');
$pdf->SetFont('helvetica', 'I', 6.5);
$pdf->Cell(65, 3.5, '(Local, Regional, National, International)', 1, 0, 'C');
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell(34.4, 3.5, '', 1, 1, 'C');

// Participation data rows (minimum 9 rows for the form)
$rowCount = max(9, count($allParticipations));
for ($i = 0; $i < $rowCount; $i++) {
    if (isset($allParticipations[$i])) {
        $p = $allParticipations[$i];
        $pdf->Cell(30, 5.5, $p['date'] ?? '', 1, 0, 'L');
        $pdf->Cell(75, 5.5, substr($p['event_name'] ?? '', 0, 50), 1, 0, 'L');
        $pdf->Cell(65, 5.5, $p['level'] ?? '', 1, 0, 'L');
        $pdf->Cell(34.4, 5.5, $p['rank_award'] ?? '', 1, 1, 'L');
    } else {
        $pdf->Cell(30, 5.5, '', 1, 0, 'L');
        $pdf->Cell(75, 5.5, '', 1, 0, 'L');
        $pdf->Cell(65, 5.5, '', 1, 0, 'L');
        $pdf->Cell(34.4, 5.5, '', 1, 1, 'L');
    }
}

$pdf->SetFont('helvetica', 'I', 7);
$pdf->Cell(0, 3, 'Add row as needed', 0, 1, 'L');

$pdf->Ln(2);

// ========================================
// IV. AFFILIATION TO ORGANIZATIONS
// ========================================
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(0, 5.5, 'IV. AFFILIATION TO ORGANIZATIONS', 1, 1, 'L', true);

// Table headers
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(50, 5.5, 'Position', 1, 0, 'C', true);
$pdf->Cell(110, 5.5, 'Name of Organization', 1, 0, 'C', true);
$pdf->Cell(44.4, 5.5, 'Inclusive Years', 1, 1, 'C', true);

// Affiliation data rows (minimum 5 rows)
$pdf->SetFont('helvetica', '', 8);
$affiliationRows = max(5, count($affiliations));
for ($i = 0; $i < $affiliationRows; $i++) {
    if (isset($affiliations[$i])) {
        $a = $affiliations[$i];
        $pdf->Cell(50, 5.5, $a['position'] ?? '', 1, 0, 'L');
        $pdf->Cell(110, 5.5, substr($a['organization'] ?? '', 0, 60), 1, 0, 'L');
        $pdf->Cell(44.4, 5.5, $a['years_active'] ?? '', 1, 1, 'L');
    } else {
        $pdf->Cell(50, 5.5, '', 1, 0, 'L');
        $pdf->Cell(110, 5.5, '', 1, 0, 'L');
        $pdf->Cell(44.4, 5.5, '', 1, 1, 'L');
    }
}

$pdf->Ln(3);

// ========================================
// CERTIFICATION AND SIGNATURE
// ========================================
$pdf->Ln(3);
$pdf->SetFont('helvetica', '', 8);

// Draw certification checkbox and text
$y = $pdf->GetY();
$pdf->Rect(6.6, $y + 0.8, 3, 3);
$pdf->Line(7, $y + 2, 8, $y + 3);
$pdf->Line(8, $y + 3, 9.6, $y + 1);
$certText = '      I hereby certify that all information in this form is true and correct. Any misrepresentation of facts will render this invalid and immediately disqualifies my membership to the cultural group.';
$pdf->MultiCell(0, 5, $certText, 0, 'L');

$pdf->Ln(4);
$pdf->Ln(6);
$pdf->Cell(0, 5, '_______________________________________________', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(0, 5, 'Signature over Printed Name of Performer/Member', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 5, 'Date: ' . date('F d, Y'), 0, 1, 'C');

$pdf->Ln(2);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->Cell(0, 4, '*Required Attachments: Certified True Copy of Certificates and Recognitions', 0, 1, 'L');

// Output PDF - 'D' forces download
$filename = 'Performers_Profile_Form_' . ($app['sr_code'] ?? 'Student') . '.pdf';
$pdf->Output($filename, 'D');
exit();
