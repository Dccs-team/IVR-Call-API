# Using the IVR API Simple SDK with Core PHP

This guide explains how to use the IVR API Simple SDK in a core PHP environment without any frameworks.

## Installation

The SDK is designed to be lightweight and easy to use. It consists of a single file that can be included directly in your PHP project:

```php
require_once 'path/to/ivr_caller.php';
```

## Requirements

- PHP 5.6 or higher
- The `allow_url_fopen` directive enabled in php.ini
- JSON extension (typically enabled by default)

## Basic Usage

### Initialize the Client

First, create an instance of the `IvrCaller` class with your API key and the base URL of the IVR API:

```php
$apiKey = 'your_api_key'; // Replace with your actual API key
$baseUrl = 'https://api.ringstormbot.info';
$ivrCaller = new IvrCaller($baseUrl, $apiKey);
```

### Making a Call

To initiate a call to a phone number:

```php
try {
    // Call with an audio URL
    $result = $ivrCaller->makeCall('123456789', 'https://example.com/message.mp3');
    
    // Or use the default audio message
    // $result = $ivrCaller->makeCall('123456789');
    
    $requestId = $result['request_id'];
    echo "Call initiated with request ID: " . $requestId . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### Checking Call Status

To check the status of a call:

```php
try {
    $status = $ivrCaller->checkCallStatus($requestId);
    echo "Call status: " . $status['status'] . "\n";
    
    if (isset($status['duration'])) {
        echo "Call duration: " . $status['duration'] . " seconds\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

## Advanced Usage

### Monitoring Call Progress

The SDK includes a convenient method to monitor a call until it completes:

```php
$finalStatus = $ivrCaller->pollCallStatus(
    $requestId,
    5,           // Check every 5 seconds
    24,          // For up to 2 minutes (24 * 5 seconds)
    function($status) {
        // This callback will be called on each status update
        echo "Status: " . $status['status'];
        if (isset($status['message'])) {
            echo " - " . $status['message'];
        }
        echo "\n";
    }
);

// Print final call details
if ($finalStatus) {
    echo "Call completed with status: " . $finalStatus['status'] . "\n";
    echo "Duration: " . ($finalStatus['duration'] ?? 'N/A') . " seconds\n";
}
```

## Complete Example

Here's a complete example of making a call and tracking its progress:

```php
<?php
// Include the SDK
require_once 'ivr_caller.php';

// Replace with your actual API key and server URL
$apiKey = 'your_api_key';
$baseUrl = 'https://api.ringstormbot.info';

// Replace with the actual phone number and audio URL
$phoneNumber = '1234567890';
$audioUrl = 'https://example.com/message.mp3';

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
            echo "Failed to get final call status after multiple attempts.\n";
        }
    } else {
        echo "Error: " . ($result['message'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

## Error Handling

The SDK uses exceptions for error handling. All API requests are wrapped in try-catch blocks:

```php
try {
    $result = $ivrCaller->makeCall('123456789');
    // Process result...
} catch (Exception $e) {
    // Handle error...
    echo "Error: " . $e->getMessage() . "\n";
}
```

Common errors include:
- Invalid API key
- Network connectivity issues
- Rate limiting (too many calls)
- Audio file access issues
- Server errors

## API Reference

### Class: IvrCaller

#### Constructor
```php
__construct($baseUrl, $apiKey)
```
- `$baseUrl`: The base URL of the IVR API server
- `$apiKey`: Your API key for authentication

#### Methods

##### makeCall
```php
makeCall($phoneNumber, $audioUrl = '')
```
- `$phoneNumber`: The destination phone number
- `$audioUrl`: (Optional) URL to the audio file to play
- Returns: Array containing API response with request_id

##### checkCallStatus
```php
checkCallStatus($requestId)
```
- `$requestId`: The request ID of the call
- Returns: Array containing call status information

##### pollCallStatus
```php
pollCallStatus($requestId, $interval = 5, $maxAttempts = 12, $callback = null)
```
- `$requestId`: The request ID of the call to monitor
- `$interval`: Polling interval in seconds (default: 5)
- `$maxAttempts`: Maximum number of polling attempts (default: 12)
- `$callback`: Optional callback function for status updates
- Returns: Final call status or null if max attempts reached

## Best Practices

1. **Error Handling**: Always use try-catch blocks to handle potential errors.
2. **API Key Security**: Keep your API key secure and never expose it in client-side code.
3. **Rate Limiting**: Be mindful of API rate limits when making multiple calls.
4. **Audio Files**: Use clear, high-quality audio files under 60 seconds for best results.
5. **Phone Numbers**: Use E.164 format for phone numbers when possible (e.g., +1234567890).
6. **Polling Intervals**: Use reasonable polling intervals (5-10 seconds) to avoid excessive API requests.
