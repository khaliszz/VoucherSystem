<?php
// Simple image proxy to handle CORS issues with Google profile images
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

if (!isset($_GET['url']) || empty($_GET['url'])) {
    // Return a default avatar if no URL provided
    $default_avatar = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxjaXJjbGUgY3g9IjUwIiBjeT0iNTAiIHI9IjUwIiBmaWxsPSIjRjVGNUY1Ii8+CjxjaXJjbGUgY3g9IjUwIiBjeT0iNDAiIHI9IjE1IiBmaWxsPSIjOTk5Ii8+CjxwYXRoIGQ9Ik0yMCA4MEMyMCA2NS42NDA2IDMxLjY0MDYgNTQgNDYgNTRINTRDNjguMzU5NCA1NCA4MCA2NS42NDA2IDgwIDgwVjEwMEgyMFY4MFoiIGZpbGw9IiM5OTkiLz4KPC9zdmc+';
    echo base64_decode(str_replace('data:image/svg+xml;base64,', '', $default_avatar));
    exit;
}

$image_url = $_GET['url'];

// Validate that it's a Google profile image URL
if (!preg_match('/^https:\/\/lh3\.googleusercontent\.com\//', $image_url)) {
    // Return default avatar for non-Google URLs
    $default_avatar = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxjaXJjbGUgY3g9IjUwIiBjeT0iNTAiIHI9IjUwIiBmaWxsPSIjRjVGNUY1Ii8+CjxjaXJjbGUgY3g9IjUwIiBjeT0iNDAiIHI9IjE1IiBmaWxsPSIjOTk5Ii8+CjxwYXRoIGQ9Ik0yMCA4MEMyMCA2NS42NDA2IDMxLjY0MDYgNTQgNDYgNTRINTRDNjguMzU5NCA1NCA4MCA2NS42NDA2IDgwIDgwVjEwMEgyMFY4MFoiIGZpbGw9IiM5OTkiLz4KPC9zdmc+';
    echo base64_decode(str_replace('data:image/svg+xml;base64,', '', $default_avatar));
    exit;
}

// Set up cURL to fetch the image
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $image_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$image_data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 && $image_data !== false) {
    echo $image_data;
} else {
    // Return default avatar if image fetch fails
    $default_avatar = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxjaXJjbGUgY3g9IjUwIiBjeT0iNTAiIHI9IjUwIiBmaWxsPSIjRjVGNUY1Ii8+CjxjaXJjbGUgY3g9IjUwIiBjeT0iNDAiIHI9IjE1IiBmaWxsPSIjOTk5Ii8+CjxwYXRoIGQ9Ik0yMCA4MEMyMCA2NS42NDA2IDMxLjY0MDYgNTQgNDYgNTRINTRDNjguMzU5NCA1NCA4MCA2NS42NDA2IDgwIDgwVjEwMEgyMFY4MFoiIGZpbGw9IiM5OTkiLz4KPC9zdmc+';
    echo base64_decode(str_replace('data:image/svg+xml;base64,', '', $default_avatar));
}
?>
