<?php
require_once 'config.php';

class CurlRequest {
    private $url;
    private $headers = [];
    private $timeout = null;
    private $authToken = null;
    private $api_key = null;
    private $cookie = null;
    public function __construct($url) {
        global $request_exec_timeout;
        $this->url = $url;
        $this->timeout = $request_exec_timeout;
    }

    public function setTimeout($seconds) {
        $this->timeout = $seconds;
    }

    public function setHeaders(array $headers) {
        $this->headers = array_merge($this->headers, $headers);
    }

    public function setBearerToken($token) {
        $this->authToken = $token;
    }
    
    public function api_key($token) {
        $this->api_key = $token;
    }

    public function setCookie($cookieStr) {
        $this->cookie = $cookieStr;
    }

    private function prepareHeaders() {
        $headers = $this->headers;

        if ($this->authToken) {
            $headers[] = "Authorization: Bearer {$this->authToken}";
        }
        if ($this->api_key) {
            $headers[] = $this->api_key;
        }

        return $headers;
    }

    private function execute($method, $data = null) {
        $this->timeout = !$this->timeout  ?  30000 : $this->timeout;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $finalHeaders = $this->prepareHeaders();
        if (!empty($finalHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);
        }
        if ($this->cookie) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);   
        }
        if ($data) {
            if (is_array($data)) {
                $data = http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $durationMs = round((microtime(true) - $startTime) * 1000);
        
        $error = null;
        $httpCode = null;
        if (curl_errno($ch)) {
            $error = curl_error($ch);
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        curl_close($ch);

        // --- API Logging ---
        $log_file = __DIR__ . '/api_debug.php';
        
        // Log rotation: reset if larger than 5MB
        if (file_exists($log_file) && filesize($log_file) > 5 * 1024 * 1024) {
            unlink($log_file);
        }
        
        // Ensure file is not directly readable via web (Security)
        if (!file_exists($log_file)) {
            file_put_contents($log_file, "<?php die('Forbidden'); ?>" . PHP_EOL);
        }

        $log_entry = [
            'time' => date('Y-m-d H:i:s'),
            'method' => strtoupper($method),
            'url' => $this->url,
            'payload' => $data,
            'status' => $httpCode,
            'error' => $error,
            'response' => $response,
            'duration_ms' => $durationMs
        ];
        
        file_put_contents($log_file, json_encode($log_entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($error) {
            return [
                'status' => null,
                'body' => null,
                'error' => $error,
            ];
        }

        return [
            'status' => $httpCode,
            'body' => $response
        ];
    }

    public function get() {
        return $this->execute("GET");
    }

    public function post($data) {
        return $this->execute("POST", $data);
    }

    public function put($data) {
        return $this->execute("PUT", $data);
    }

    public function delete($data = null) {
        return $this->execute("DELETE", $data);
    }
    public function PATCH($data = null){
        return $this->execute('PATCH',$data);
    }
}