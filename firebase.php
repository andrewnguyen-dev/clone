<?php

    class FirebaseNotification {

        public static function sendFcmNotification($userTokens, $body, $title, $data, $sound, $androidChannel, $supplierId){
            foreach($userTokens as $userToken){
                self::_sendFcmNotification($userToken, $body, $title, $data, $sound, $androidChannel, $supplierId);
            }
        }

        private static function _sendFcmNotification($userToken, $body, $title, $data, $sound, $androidChannel, $supplierId){
            global $firebaseProjectId, $techEmail, $techName;
    
            $fields = [
                'message' => [
                    'token' => $userToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                    'android' => [
                        'notification' => [
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => $sound,
                            'channel_id' => $androidChannel,
                        ],
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'category' => 'FLUTTER_NOTIFICATION_CLICK',
                                'sound' => $sound,
                            ],
                        ],
                    ],
                ],
            ];
    
            $headers = array (
                'Authorization: Bearer ' . FirebaseNotification::getOAuth2Token(),
                'Content-Type: application/json'
            );
    
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/$firebaseProjectId/messages:send" );
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $fields ) );
            $result = curl_exec($ch );
            curl_close( $ch );
            
            $resultArray = json_decode($result, true);
            $error = isset($resultArray['error']) ? $resultArray['error']['details'][0] : null;
            $errorCode = isset($error['errorCode']) ? $error['errorCode'] : (isset($error['reason']) ? $error['reason'] : null);
            if ($errorCode && $errorCode == 'UNREGISTERED') {
                SendMail($techEmail, $techName, 'Invalid Firebase Token', "Supplier: $supplierId\nToken: $userToken\nResult: $result");
                throw new InvalidFirebaseTokenException($userToken);
            }

            if(!is_null($error)) {
                SendMail($techEmail, $techName, 'firebase notification error ' . $supplierId, $result);
            }
        }


        private static function getOAuth2Token() {
            global $phpdir, $firebaseServiceAccountPath;
            $tokenDir = "$phpdir/google_auth/.firebase_token";

            $oauth2Token = trim(shell_exec("$phpdir/google_auth/oauth2l fetch --cache $tokenDir --credentials $firebaseServiceAccountPath --scope firebase.messaging"));
            if(strpos($oauth2Token,'permission denied') !== false){
                throw new Exception($oauth2Token);
            }
            
            return $oauth2Token;
        }
    }

    class InvalidFirebaseTokenException extends Exception {
        public $token;
        public function __construct($token) {
            $this->token = $token;
            parent::__construct("Invalid Firebase Token: $token");
        }
    }