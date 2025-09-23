<?php
ob_start();
session_start();
require_once 'connection.php';
require_once 'fpdf/fpdf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: homepage.php");
    exit;
}

$userId = $_SESSION['user_id'];
$pdf = new FPDF();
$pdf->SetAutoPageBreak(true, 20);

// --- Function: Draw Voucher Layout ---
function drawVoucher($pdf, $voucher, $i) {
    // New page
    //$pdf->AddPage();
    $pdf->SetAutoPageBreak(false); // prevent auto page break

    // Background
    $pdf->SetFillColor(240, 230, 255);
    $pdf->Rect(0, 0, 210, 297, 'F');

    // --- Header ---
    if (file_exists("images/logo.png")) {
        $pdf->Image("images/logo.png", 10, 8, 25);
    }
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(128, 0, 128);
    $pdf->Cell(0, 15, "Voucher", 0, 1, 'C');
    $pdf->Ln(5);

    // --- Title ---
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(40, 167, 69);
    $pdf->Cell(0, 12, $voucher['title'], 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(5);

    // --- Image ---
    if (!empty($voucher['image']) && file_exists($voucher['image'])) {
        $pdf->Image($voucher['image'], 60, 55, 90, 60);
        $pdf->SetY(125); // go below image
    } else {
        $pdf->Ln(20);
    }

    // --- Voucher Code ---
    $pdf->SetFont('Arial','B',14);
    $pdf->SetFillColor(220,220,220);
    $pdf->Cell(0,12,"Voucher Code: VC" . $voucher['history_id'] . "-$i",0,1,'C',true);
    $pdf->Ln(8);

    // --- Details ---
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(60,10,"Points Used:",0,0,'L');  
    $pdf->Cell(0,10,$voucher['points'],0,1,'L');

    $pdf->Cell(60,10,"Completed Date:",0,0,'L');
    $pdf->Cell(0,10,date("d M Y", strtotime($voucher['completed_date'])),0,1,'L');

    $pdf->Cell(60,10,"Expiry Date:",0,0,'L');
    $pdf->Cell(0,10,date("d M Y", strtotime($voucher['expiry_date'])),0,1,'L');
    $pdf->Ln(8);

    // --- Terms ---
    if (!empty($voucher['terms_and_condition'])) {
    $pdf->SetFont('Arial','I',11);
    $pdf->Cell(0,7,"Terms & Conditions:",0,1);

    // Split sentences by "." and trim spaces
    $sentences = preg_split('/\.\s*/', $voucher['terms_and_condition'], -1, PREG_SPLIT_NO_EMPTY);

    foreach ($sentences as $s) {
        $s = trim($s);
        if (!empty($s)) {
            $pdf->MultiCell(0,7,"- " . $s . ".",0,'L');
        }
    }
}


    // --- Footer (always at fixed position) ---
    $pdf->SetY(280);
    $pdf->SetFont('Arial','I',8);
    $pdf->Cell(0,10,"Generated on " . date("d M Y"),0,0,'C');
}


// From Checkout (multiple vouchers)
if (isset($_GET['mode']) && $_GET['mode'] === 'recent' && !empty($_SESSION['recent_history_ids'])) {
    $ids = $_SESSION['recent_history_ids'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "SELECT h.history_id, v.title, v.image, v.points, v.terms_and_condition, h.quantity, h.completed_date, h.expiry_date
            FROM cart_item_history h
            JOIN voucher v ON h.voucher_id = v.voucher_id
            WHERE h.user_id=? AND h.history_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge([$userId], $ids));
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($vouchers as $voucher) {
        for ($i = 1; $i <= $voucher['quantity']; $i++) {
            $pdf->AddPage();
            drawVoucher($pdf, $voucher, $i);
        }
    }

    $pdf->Output('D', 'Recent_Vouchers.pdf');
    ob_end_flush();
    exit;
}

// From Voucher History (single voucher, multi-page if quantity > 1)
if (isset($_GET['id'])) {
    $historyId = intval($_GET['id']);

    $sql = "SELECT h.history_id, v.title, v.image, v.points, v.terms_and_condition, h.quantity, h.completed_date, h.expiry_date
            FROM cart_item_history h
            JOIN voucher v ON h.voucher_id = v.voucher_id
            WHERE h.user_id=? AND h.history_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $historyId]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        header("Location: voucher_history.php");
        exit;
    }

    $now = new DateTime();
    $expiry = new DateTime($voucher['expiry_date']);
    if ($now > $expiry) {
        header("Location: voucher_history.php");
        exit;
    }

    for ($i = 1; $i <= $voucher['quantity']; $i++) {
        $pdf->AddPage();
        drawVoucher($pdf, $voucher, $i);
    }

    $pdf->Output('D', 'Voucher_' . $voucher['history_id'] . '.pdf');
    ob_end_flush();
    exit;
}

// --- Default: Go home ---
header("Location: homepage.php");
exit;
