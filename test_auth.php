<?php
// Script de prueba para verificar autenticación
echo "Testing Laravel Sanctum Authentication\n";
echo "=====================================\n";

// Test 1: Login
$loginData = [
    'email' => 'superadmin@test.com',
    'password' => 'password123'
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'http://34.133.151.154:8000/api/login',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($loginData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_VERBOSE => true
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

echo "Login Test:\n";
echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['access_token'])) {
        $token = $data['access_token'];
        echo "Token obtained: " . substr($token, 0, 20) . "...\n";
        
        // Test 2: Protected endpoint
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'http://34.133.151.154:8000/api/surveys',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json'
            ]
        ]);
        
        $response2 = curl_exec($curl);
        $httpCode2 = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        echo "\nProtected Endpoint Test:\n";
        echo "HTTP Code: $httpCode2\n";
        echo "Response length: " . strlen($response2) . " characters\n";
        
        if ($httpCode2 === 200) {
            echo "✅ Authentication working correctly!\n";
        } else {
            echo "❌ Authentication failed on protected endpoint\n";
            echo "Response: $response2\n";
        }
    }
} else {
    echo "❌ Login failed\n";
}
?>