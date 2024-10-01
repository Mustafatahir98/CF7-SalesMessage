<?php
/*
Plugin Name: CF7 Salesmessage Integration
Description: Send an SMS message using SalesMessage with OAuth2 client credentials flow when a form hits a submit button and validation fails. Includes detailed error logging and form data in the message.
Version: 1.2
Author: Alycom Business Solutions
Author URI: http://personaldrivers.com
*/

add_action('wpcf7_submit', 'send_sms_message_on_form_failure_with_oauth2', 10, 2);

function send_sms_message_on_form_failure_with_oauth2($contact_form, $result) {
    if ($result['status'] === 'mail_sent') {
        $submission = WPCF7_Submission::get_instance();

        if ($submission) {
            $posted_data = $submission->get_posted_data();

            // Extract relevant form fields
            $firstName = isset($posted_data['FirstName']) ? $posted_data['FirstName'] : '';
            $lastName = isset($posted_data['LastName']) ? $posted_data['LastName'] : '';
            $email = isset($posted_data['Email']) ? $posted_data['Email'] : '';
            $phone = isset($posted_data['phone']) ? $posted_data['phone'] : '';
            $fromCity = isset($posted_data['fromCity']) ? $posted_data['fromCity'] : '';
            $stateFrom = isset($posted_data['statefrom']) ? $posted_data['statefrom'] : '';
            $fromZip = isset($posted_data['fromZip']) ? $posted_data['fromZip'] : '';
            $toCity = isset($posted_data['toCity']) ? $posted_data['toCity'] : '';
            $stateTo = isset($posted_data['stateto']) ? $posted_data['stateto'] : '';
            $toZip = isset($posted_data['toZip']) ? $posted_data['toZip'] : '';

            // Function to log error and restrict form submission
            function restrict_submission($reason) {
                error_log('Form submission restricted: ' . $reason);
                return;
            }

            // Check if any of the restricted values are present
            if (
                strcasecmp($firstName, 'Dilshad') === 0 ||
                strcasecmp($lastName, 'Delawalla') === 0 ||
                strcasecmp($phone, '2142282287') === 0 ||
                strcasecmp($fromCity, 'Dallas') === 0 ||
                strcasecmp($stateFrom, 'Texas') === 0 ||
                strcasecmp($email, 'dilshad@makecustomersforlife.com') === 0 ||
                strcasecmp($address, '865 Meadow Drive Arlington, TX 76014') === 0
            ) {
                restrict_submission('Restricted value found in form submission.');
                return;
            }

            // Construct message body with form data
$messageBody = "Form submission:\n\n";

if (!empty($firstName) || !empty($lastName)) {
    $messageBody .= "Name: $firstName $lastName\n";
}
if (!empty($email)) {
    $messageBody .= "Email: $email\n";
}
if (!empty($phone)) {
    $messageBody .= "Phone: $phone\n";
}
if (!empty($fromCity) || !empty($stateFrom) || !empty($fromZip)) {
    $messageBody .= "Journey Information:\n";
    if (!empty($fromCity)) {
        $messageBody .= "From City: $fromCity\n";
    }
    if (!empty($stateFrom)) {
        $messageBody .= "From State: $stateFrom\n";
    }
    if (!empty($fromZip)) {
        $messageBody .= "From Zip: $fromZip\n";
    }
}
if (!empty($toCity) || !empty($stateTo) || !empty($toZip)) {
    if (empty($fromCity) && empty($stateFrom) && empty($fromZip)) {
        $messageBody .= "Journey Information:\n";  // Add this line if no 'From' info was added
    }
    if (!empty($toCity)) {
        $messageBody .= "To City: $toCity\n";
    }
    if (!empty($stateTo)) {
        $messageBody .= "To State: $stateTo\n";
    }
    if (!empty($toZip)) {
        $messageBody .= "To Zip: $toZip\n";
    }
}


            // Get access token using OAuth2
            $accessToken = get_salesmessage_access_token();
            if (!$accessToken) {
                error_log('SalesMessage Access Token Error: Unable to retrieve access token.');
                return;
            }

            // Use the access token to send an SMS
            $sendMessageUrl = 'https://api.salesmessage.com/pub/v2.1/messages/';
            $ch = curl_init($sendMessageUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'to' => '', // The recipient's phone number
                'from' => '', // Your SalesMessage phone number
                'message' => $messageBody
            ]));

            $messageResponse = curl_exec($ch);
            $err = curl_error($ch);
            if ($err) {
                error_log("SalesMessage Send Message Error: " . $err);
            } else {
                $responseDetails = json_decode($messageResponse, true);
                error_log("SalesMessage API Full Response: " . print_r($responseDetails, true));

                if (isset($responseDetails['error'])) {
                    error_log("SalesMessage Send Message Error: " . $responseDetails['error']['message']);
                } else {
                    error_log("SalesMessage Message Sent Successfully" . $messageBody);
                }
            }
            curl_close($ch);
        }
    }
}

function get_salesmessage_access_token() {
    $tokenUrl = '';
    $refreshTokenUrl = '';
    $clientId = '';
    $clientSecret = '';
    $redirectUri = '';
    $authorizationCode = get_option('salesmessage_authorization_code');
    $refreshToken = get_option('salesmessage_refresh_token');
    $accessToken = get_option('salesmessage_access_token');
    $tokenExpiration = get_option('salesmessage_token_expiration');

    // Check if the access token is still valid
    if ($accessToken && $tokenExpiration) {
        $timeLeft = $tokenExpiration - time();
        if ($timeLeft > 0) {
            error_log('Using stored access token. Time left before expiration: ' . $timeLeft . ' seconds.');
            return $accessToken;
        } else {
            error_log('Stored access token has expired. Refreshing token.');
        }
    }

    if ($refreshToken) {
        // Refresh the access token
        error_log('Attempting to refresh access token with refresh token at ' . date('Y-m-d H:i:s'));
        $response = wp_remote_post($refreshTokenUrl, [
            'body' => [
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'scope' => 'public-api'
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);
    } else {
        // Exchange authorization code for access token
        error_log('Attempting to exchange authorization code for access token at ' . date('Y-m-d H:i:s'));
        $response = wp_remote_post($tokenUrl, [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $authorizationCode,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);
    }

    if (is_wp_error($response)) {
        error_log('SalesMessage Token Request Error: ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    error_log('SalesMessage Token Response: ' . print_r($data, true));

    if (isset($data['error'])) {
        error_log('SalesMessage Token Response Error: ' . $data['error_description']);
        return false;
    }

    // Save new access token, refresh token, and expiration time if available
    if (isset($data['access_token']) && isset($data['refresh_token']) && isset($data['expires_in'])) {
        store_salesmessage_tokens($data['access_token'], $data['refresh_token'], $data['expires_in']);
    }

    return $data['access_token'] ?? false;
}

function store_salesmessage_tokens($accessToken, $refreshToken, $expiresIn) {
    $expirationTime = time() + intval($expiresIn); // Calculate the expiration timestamp
    update_option('salesmessage_access_token', sanitize_text_field($accessToken));
    update_option('salesmessage_refresh_token', sanitize_text_field($refreshToken));
    update_option('salesmessage_token_expiration', $expirationTime);
    error_log('Access and Refresh Tokens stored. Expires at: ' . date('Y-m-d H:i:s', $expirationTime));
}

// Handle the callback and store the authorization code
function handle_salesmessage_callback() {
    if (isset($_GET['code'])) {
        $authorizationCode = sanitize_text_field($_GET['code']);
        update_option('salesmessage_authorization_code', $authorizationCode);
        error_log('Authorization Code received and stored: ' . $authorizationCode);
    }
}
add_action('init', 'handle_salesmessage_callback');
?>
