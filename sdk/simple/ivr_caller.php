<?php
/**
 * IVR Caller - Simple PHP Client
 * 
 * A lightweight PHP client for making calls with the IVR API
 * Includes only the core call functionality without admin features
 */

class IvrCaller {
    /**
     * @var string Base URL for API requests
     */
    private $baseUrl;
    
    /**
     * @var string API key for authentication
     */
    private $apiKey;
    
    /**
     * Constructor
     * 
     * @param string $baseUrl The base URL of the IVR API server
     * @param string $apiKey Your API key for authentication
     */
    public function __construct($baseUrl, $apiKey) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }
    
    /**
     * Make a phone call with audio
     * 
     * @param string $phoneNumber The destination phone number
     * @param string $audioUrl URL to the audio file to play (optional)
     * @return array Response from the API containing request_id
     * @throws Exception If the API request fails
     */
    public function makeCall($phoneNumber, $audioUrl = '') {
        $data = [
            'api_key' => $this->apiKey,
            'number' => $phoneNumber
        ];
        
        if (!empty($audioUrl)) {
            $data['audio_url'] = $audioUrl;
        }
        
        return $this->sendRequest('POST', '/api/make_call', $data);
    }
    
    /**
     * Check the status of a call
     * 
     * @param string $requestId The request ID of the call
     * @return array Response from the API containing call status
     * @throws Exception If the API request fails
     */
    public function checkCallStatus($requestId) {
        $params = [
            'api_key' => $this->apiKey,
            'request_id' => $requestId
        ];
        
        return $this->sendRequest('GET', '/api/call_status', null, $params);
    }
    
    /**
     * Poll for call status until completion or max attempts reached
     * 
     * @param string $requestId The request ID of the call to monitor
     * @param int $interval Polling interval in seconds (default: 5)
     * @param int $maxAttempts Maximum number of polling attempts (default: 12)
     * @param callable $callback Optional callback function for status updates
     * @return array|null Final call status or null if max attempts reached
     */
    public function pollCallStatus($requestId, $interval = 5, $maxAttempts = 12, $callback = null) {
        $terminalStates = ['completed', 'error', 'no_answer', 'timeout', 'disconnected'];
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            try {
                $result = $this->checkCallStatus($requestId);
                
                // Call the callback if provided
                if (is_callable($callback)) {
                    call_user_func($callback, $result);
                }
                
                // Check if call has reached a terminal state
                if (isset($result['status']) && in_array($result['status'], $terminalStates)) {
                    return $result;
                }
                
                // Wait before checking again
                sleep($interval);
                $attempts++;
                
            } catch (Exception $e) {
                if (is_callable($callback)) {
                    call_user_func($callback, ['status' => 'error', 'message' => $e->getMessage()]);
                }
                $attempts++;
                sleep(1); // Short sleep before retry on error
            }
        }
        
        return null; // Max attempts reached
    }
    
    /**
     * Send an HTTP request to the API
     * 
     * @param string $method HTTP method (GET, POST)
     * @param string $endpoint API endpoint
     * @param array $data Request data for POST requests
     * @param array $params Query parameters for GET requests
     * @return array Response from the API
     * @throws Exception If request fails
     */
    private function sendRequest($method, $endpoint, $data = null, $params = []) {
        $url = $this->baseUrl . $endpoint;
        
        // Add query parameters for GET requests
        if (!empty($params) && $method === 'GET') {
            $url .= '?' . http_build_query($params);
        }
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => 'Content-Type: application/json',
                'ignore_errors' => true
            ]
        ];
        
        // Add data for POST requests
        if ($data !== null && $method === 'POST') {
            $options['http']['content'] = json_encode($data);
        }
        
        // Create context and send request
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        // Check for HTTP errors
        if ($response === false) {
            throw new Exception('API request failed');
        }
        
        // Parse HTTP status code
        $status_line = $http_response_header[0];
        preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
        $status = $match[1];
        
        // Decode JSON response
        $result = json_decode($response, true);
        
        // Handle API errors
        if ($status >= 400) {
            $message = isset($result['message']) ? $result['message'] : 'Unknown API error';
            throw new Exception("API error ({$status}): {$message}");
        }
        
        return $result;
    }
}

/**
 * Example usage:
 */
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    // This code runs only when the file is executed directly, not when included
    
    // Replace with your actual API key and server URL
    $apiKey = 'apikey';
    $baseUrl = 'https://api.ringstormbot.info';
    
    // Replace with the actual phone number and audio URL
    $phoneNumber = '+88123456789';
    $audioUrl = 'audio url';
    
    try {
        // Initialize the client
        $ivrCaller = new IvrCaller($baseUrl, $apiKey);
        
        // Make a call
        echo "Making call to {$phoneNumber}...\n";
        $result = $ivrCaller->makeCall($phoneNumber, $audioUrl);
        
        if (isset($result['request_id'])) {
            $requestId = $result['request_id'];
            echo "Call initiated with request ID: {$requestId}\n\n";
            
            // Monitor call progress
            echo "Tracking call progress...\n";
            $finalStatus = $ivrCaller->pollCallStatus(
                $requestId,
                5, // Check every 5 seconds
                24, // For up to 2 minutes (24 * 5 seconds)
                function($status) {
                    echo "Status: " . $status['status'];
                    if (isset($status['message'])) {
                        echo " - " . $status['message'];
                    }
                    echo "\n";
                }
            );
            
            if ($finalStatus) {
                echo "\nCall Summary:\n";
                echo "  Status: " . $finalStatus['status'] . "\n";
                echo "  Duration: " . ($finalStatus['duration'] ?? 'N/A') . "s\n";
                echo "  Message: " . ($finalStatus['message'] ?? 'N/A') . "\n";
                
                // Print audio details if available
                if (isset($finalStatus['audio_played'])) {
                    echo "  Audio Played: " . ($finalStatus['audio_played'] ? 'Yes' : 'No') . "\n";
                }
                if (isset($finalStatus['audio_completed'])) {
                    echo "  Audio Completed: " . ($finalStatus['audio_completed'] ? 'Yes' : 'No') . "\n";
                }
            } else {
                echo "Faialed to get final call status after multiple attempts.\n";
            }
        } else {
            echo "Error: " . ($result['message'] ?? 'Unknown error') . "\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
