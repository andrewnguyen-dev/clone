<?php

class Basecamp {

    public $account_id;
    public $project_id;
    public $app_name;
    public $app_owner;
    public $auth;

    public function __construct() {
        global $bcAccountID, $bcProjectID, $bcAppName, $bcAppOwner;
        $this->account_id = $bcAccountID;
        $this->project_id = $bcProjectID;
        $this->app_name = $bcAppName;
        $this->app_owner = $bcAppOwner;
        $this->auth = new BasecampAuth();
    }

    public function headers() {
        return [
            "Authorization: Bearer ".$this->auth->token(),
            "Content-Type:application/json",
            "User-Agent: ".$this->app_name." (".$this->app_owner.")",
        ];
    }

    public function new_campfire_message($campfire_id, $message) {
        $endpoint = "buckets/".$this->project_id."/chats/".$campfire_id."/lines.json";
        $payload = ["content" => $message];
        $response = $this->request($endpoint, "post", $payload);
        $response = json_decode($response, true);
        if(is_array($response) && isset($response['error'])){
            error_log('Error sending message to Basecamp: ' . json_encode($response));
        }
        return (is_array($response) && isset($response['id']) && $response['id'] > 1);
    }

    public function new_todo_item($todoListId, $content, $description, $assigneeIds) {
		$endpoint = "buckets/".$this->project_id."/todolists/".$todoListId."/todos.json";
		$payload = [
			'content' => $content,
			'description' => $description,
			'due_on' => date('Y-m-d', strtotime('+14 day')),
			'notify' => true,
			'assignee_ids' => $assigneeIds,
		];
		$response = $this->request($endpoint, "post", $payload);
		$response = json_decode($response, true);

		$success = (is_array($response) && isset($response['id']) && $response['id'] > 1);
		if(!$success) $this->auth->alert('failed_to_create_todo_item', json_encode($response));
		return $success;
	}

    public function request($endpoint, $method = "get", $payload = "") {
        $baseurl = "https://3.basecampapi.com/".$this->account_id."/";
        $url = $baseurl.$endpoint;
        $method = strtolower($method);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers());
        if ($method == "post") {
            $payload = is_array($payload) ? json_encode($payload) : $payload;
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

}

class BasecampAuth {

    public function token() {
        $token = db_getVal("SELECT description FROM global_data WHERE name = 'basecamptoken' LIMIT 1");
        if (empty($token)) {
            $token = $this->refresh();
        } else {
            $expiry = $this->token_expiry();
            if ($expiry - time() < 0) {
                $token = $this->refresh();
            }
        }
        return $token;
    }

    public function token_expiry() {
        $token = db_getVal("SELECT description FROM global_data WHERE name = 'basecamptokenexpiry' LIMIT 1");
        return (empty($token)) ? 0 : intval($token);
    }

    public function refresh() {
        global $bcAuthClientID, $bcAuthClientSecret, $bcAuthRedirectURL;
        $refresh = db_getVal("SELECT description FROM global_data WHERE name = 'basecamprefresh'");
        if (empty($refresh)) {
            $this->alert('refresh_token_not_found');
            return false;
        }
        $refresh_url = "https://launchpad.37signals.com/authorization/token";
        $payload = [
            "type" => "refresh",
            "refresh_token" => $refresh,
            "client_id" => $bcAuthClientID,
            "client_secret" => $bcAuthClientSecret,
            "redirect_uri" => $bcAuthRedirectURL,
        ];
        $ch = curl_init($refresh_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response, true);
        if (!empty($response)) {
            extract($response);

            if ( !(empty($access_token) || empty($expires_in)) ) {
                db_query("DELETE FROM global_data WHERE name = 'basecamptoken'");
                db_query("DELETE FROM global_data WHERE name = 'basecamptokenexpiry'");
                $this->saveAccessToken($access_token);
                $this->saveExpiresIn($expires_in);
                return $access_token;
            }
        }
		
        return false;
    }

    private function saveAccessToken($token) {
        db_query("INSERT INTO global_data (id, type, name, description, created) VALUES (1, 'basecamp_auth', 'basecamptoken', '".$token."', NOW())");
    }

    private function saveExpiresIn($expires_in) {
        db_query("INSERT INTO global_data (id, type, name, description) VALUES (1, 'basecamp_auth', 'basecamptokenexpiry', '".(time() + intval($expires_in))."')");
    }

    public function alert($type, $message = '') {
        global $techEmail, $techName;
        switch($type) {
            case 'refresh_token_not_found':
                $message = "Basecamp refresh token not found";
                break;
        }
        SendMail($techEmail, $techName, "Basecamp Error - $type", $message);
    }
}