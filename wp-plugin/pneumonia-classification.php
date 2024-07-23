<?php
/*
Plugin Name: Pneumonia Classification
Description: Integrates with Hugging Face Inference API for pneumonia classification
Version: 1.0
Author: JEFFREY MDALA LASO DIGITAL HEALTH
*/

// Shortcode to display the upload form
function pneumonia_classification_shortcode() {
    ob_start();
    ?>
    <div id="pneumonia-classification">
        <h2>Pneumonia Classification</h2>
        <form id="pneumonia-form" enctype="multipart/form-data">
            <input type="file" name="file" accept="image/*" required>
            <button type="submit">Upload</button>
        </form>
        <div id="result"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('pneumonia_classification', 'pneumonia_classification_shortcode');

// AJAX Debugging
add_action('wp_ajax_nopriv_pneumonia_classification', function() {
    error_log('AJAX request received (nopriv)');
}, 1);
add_action('wp_ajax_pneumonia_classification', function() {
    error_log('AJAX request received');
}, 1);

// Function to handle the AJAX request
function handle_pneumonia_classification() {
    error_log('Handle pneumonia classification function called');

    $hf_api_token = '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';
    $hf_api_url = 'https://api-inference.huggingface.co/models/lxyuan/vit-xray-pneumonia-classification';

    if (!isset($_FILES['file'])) {
        error_log('No file uploaded');
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
        wp_die();
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log('File upload error: ' . $file['error']);
        echo json_encode(['status' => 'error', 'message' => 'File upload error']);
        wp_die();
    }

    $image_data = file_get_contents($file['tmp_name']);
    if ($image_data === false) {
        error_log('Failed to read uploaded file');
        echo json_encode(['status' => 'error', 'message' => 'Failed to read uploaded file']);
        wp_die();
    }

    $base64_image = base64_encode($image_data);

    $headers = [
        'Authorization: Bearer ' . $hf_api_token,
        'Content-Type: application/json'
    ];

    $data = json_encode([
        'inputs' => $base64_image
    ]);

    $max_retries = 5;
    $retry_delay = 5; // seconds

    for ($i = 0; $i < $max_retries; $i++) {
        $ch = curl_init($hf_api_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            error_log('Curl error: ' . curl_error($ch));
        }
        
        curl_close($ch);

        error_log("API Response (attempt " . ($i + 1) . "): HTTP " . $http_code . " - " . $response);

        if ($http_code == 200) {
            // Success
            $result = json_decode($response, true);
            if (isset($result[0])) {
                $label = $result[0]['label'];
                $score = $result[0]['score'];
                echo json_encode([
                    'status' => 'success',
                    'message' => "Prediction: $label (Confidence: " . number_format($score * 100, 2) . "%)"
                ]);
                wp_die();
            }
        } elseif ($http_code == 503) {
            // Model is loading, wait and retry
            sleep($retry_delay);
            continue;
        } else {
            // Other error
            echo json_encode([
                'status' => 'error',
                'message' => "API Error: HTTP status $http_code, Response: $response"
            ]);
            wp_die();
        }
    }

    // If we've exhausted all retries
    echo json_encode([
        'status' => 'error',
        'message' => "Model is taking too long to load. Please try again later."
    ]);

    wp_die();
}
add_action('wp_ajax_pneumonia_classification', 'handle_pneumonia_classification');
add_action('wp_ajax_nopriv_pneumonia_classification', 'handle_pneumonia_classification');

// Enqueue scripts
function pneumonia_classification_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_add_inline_script('jquery', '
    jQuery(document).ready(function($) {
        $("#pneumonia-form").on("submit", function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            formData.append("action", "pneumonia_classification");
            
            $("#result").html("<p>Processing... Please wait.</p>");
            
            $.ajax({
                url: "' . admin_url('admin-ajax.php') . '",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log("Response received:", response);
                    try {
                        var result = JSON.parse(response);
                        if (result.status === "success") {
                            $("#result").html("<h3>Prediction:</h3><pre>" + result.message + "</pre>");
                        } else {
                            $("#result").html("<p>Error: " + result.message + "</p>");
                        }
                    } catch (e) {
                        console.error("Error parsing response:", e);
                        $("#result").html("<p>Error: Unable to process the response.</p>");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", status, error);
                    $("#result").html("<p>Error: " + error + "</p>");
                }
            });
        });
    });
    ');
}
add_action('wp_enqueue_scripts', 'pneumonia_classification_enqueue_scripts');