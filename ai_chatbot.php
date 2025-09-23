<?php
// ai_chatbot.php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');

// Check request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$userMessage = trim($input['message'] ?? '');

if (!$userMessage) {
    echo json_encode(["reply" => "Please enter a message."]);
    exit;
}

// Gemini API request
$apiKey = $_ENV['GEMINI_API_KEY'];
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

$payload = [
    "system_instruction" => [
        "parts" => [[
            "text" => "You are Optima Bank’s virtual assistant. 
            - Always answer questions about vouchers, voucher system, voucher redemption, points, and Optima Bank services.
            - If users ask about 'voucher system', explain how users can earn, redeem, and manage vouchers.
            - If the question is totally unrelated (like weather, food, or random jokes), reply: 'Sorry, I can only help with voucher redemption and Optima Bank services.'"
        ]]
    ],
    "contents" => [[
        "role" => "user",
        "parts" => [["text" => $userMessage]]
    ]]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? "Sorry, I can only help with voucher redemption and Optima Bank services.";

// Send JSON response
echo json_encode(["reply" => $reply]);
