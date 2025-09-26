<?php
// ai_chatbot.php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
require_once 'connection.php'; // DB connection

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');

// --- Check request ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$userMessage = strtolower(trim($input['message'] ?? ''));

if (!$userMessage) {
    echo json_encode(["reply" => "Please enter a message."]);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$reply = "";

// --- RULE-BASED INTENTS (use DB directly) ---
if ($userId) {
    // 🔹 1. User points
    if (strpos($userMessage, 'my points') !== false || strpos($userMessage, 'how many points') !== false) {
        $stmt = $conn->prepare("SELECT points FROM users WHERE user_id=?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $reply = "You currently have " . number_format($user['points'] ?? 0) . " points.";
    }

    // 🔹 2. Total available vouchers
    elseif (strpos($userMessage, 'total voucher') !== false || strpos($userMessage, 'available voucher') !== false) {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM voucher");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $reply = "There are " . ($row['total'] ?? 0) . " vouchers available in the system.";
    }

    // 🔹 3. Total redeemed vouchers (by user)
    elseif ((strpos($userMessage, 'redeem') !== false || strpos($userMessage, 'redeemed') !== false) 
         && strpos($userMessage, 'voucher') !== false) {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM cart_item_history WHERE user_id=?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $row['total'] ?? 0;
        if ($count > 0) {
            $reply = "You have redeemed a total of $count vouchers.";
        } else {
            $reply = "You have not redeemed any vouchers yet.";
        }
    }

    // 🔹 4. Last redeemed voucher
    elseif (strpos($userMessage, 'last voucher') !== false || strpos($userMessage, 'recent voucher') !== false) {
        $stmt = $conn->prepare("
            SELECT v.title, h.completed_date, h.expiry_date
            FROM cart_item_history h
            JOIN voucher v ON h.voucher_id = v.voucher_id
            WHERE h.user_id=?
            ORDER BY h.completed_date DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $reply = "Your last redeemed voucher was **" . htmlspecialchars($row['title']) . 
                     "**, redeemed on " . date("d M Y", strtotime($row['completed_date'])) . 
                     " and valid until " . date("d M Y", strtotime($row['expiry_date'])) . ".";
        } else {
            $reply = "You haven’t redeemed any vouchers yet.";
        }
    }
}

// --- AI HANDLING (Gemini fallback only) ---
if ($reply === "") {
    $apiKey = $_ENV['GEMINI_API_KEY'];
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

    $payload = [
        "system_instruction" => [
            "parts" => [[
                "text" => "You are Optima Bank’s official AI assistant for the Optima Voucher System.
                Your role is STRICTLY limited to answering questions about:
                - Vouchers (earning, redeeming, validity, expiry rules, status, history, totals).
                - Points (balance, usage, deduction, accumulation).
                - Voucher redemption flow and system rules.
                - Optima Bank services directly related to loyalty, rewards, and voucher management.

                CRITICAL RULES:
                1. If the user asks about the voucher system, always explain the actual system rules:
                   • Users earn points from transactions.  
                   • Points can be redeemed for vouchers.  
                   • Each voucher expires 7 days after redemption.  
                   • Redeemed vouchers are stored in cart_item_history.  
                   • Users can check their balance, total vouchers, and redemption history anytime.  

                2. If the question matches a predefined function (points, vouchers, redemption), 
                   use the backend-provided answer and do NOT invent numbers.  

                3. If the question is unrelated (weather, food, jokes, random topics), 
                   reply strictly: 'Sorry, I can only help with voucher redemption and Optima Bank services.'

                4. Always reply politely and concisely, in plain text (no code, no pseudo-queries)."
            ]]
        ],
        "contents" => [[
            "role" => "user",
            "parts" => [["text" => $userMessage]]
        ]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $reply = $data['candidates'][0]['content']['parts'][0]['text'] ??
             "Sorry, I can only help with voucher redemption and Optima Bank services.";
}

// --- Return JSON ---
echo json_encode(["reply" => nl2br($reply)]);
