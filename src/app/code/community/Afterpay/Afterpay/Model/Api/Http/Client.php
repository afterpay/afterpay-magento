<?php
class Afterpay_Afterpay_Model_Api_Http_Client
{
    protected $_url = '';
    protected $_configs = array();
    protected $_helper;

    public function __construct($url = '')
    {
        $this->_helper = Mage::helper('afterpay');

        if (!extension_loaded('curl')) {
            throw Mage::exception(
                'Afterpay_Afterpay',
                $this->_helper->__('cURL extension has to be loaded to use this Afterpay Http Client.')
            );
        }

        $this->_url = $url;
    }

    public function setConfigs($configs = array())
    {
        if (isset($configs['auth_user']) && isset($configs['auth_pwd'])) {
            $this->_configs[CURLOPT_USERPWD] = $configs['auth_user'].':'.$configs['auth_pwd'];
        }

        if (isset($configs['useragent'])) {
            $this->_configs[CURLOPT_USERAGENT] = $configs['useragent'];
        }

        if (isset($configs['timeout'])) {
            $this->_configs[CURLOPT_TIMEOUT] = $configs['timeout'];
        }
    }

    public function request($method = 'GET', $data = array())
    {
        $opts = $this->_configs + array(
            CURLOPT_URL => $this->_url,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
            ),
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_TIMEOUT => 80,
        );

        if (!empty($data)) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($data);
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Length: '.strlen($opts[CURLOPT_POSTFIELDS]);

            switch (strtoupper($method)) {
                case 'POST':
                    $opts[CURLOPT_POST] = TRUE;
                    break;
                case 'PUT':
                    $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
                    break;
            }
        }

        $ch = curl_init();

        if (!curl_setopt_array($ch, $opts)) {
            // If an option could not be successfully set
            curl_close($ch);
            $this->_helper->log('Unable to set cURL with options:');
            $this->_helper->log($opts);

            throw Mage::exception('Afterpay_Afterpay', $this->_helper->__('Unable to set options for cURL.'));
        }

        $output = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);

        if ($errno) {
            // i.e. It cannot get a response
            $this->_helper->log('cURL error number: ' . $errno . ', error message: ' . $error);
            $output = json_encode(array(
                'errorCode' => $errno,
                'errorId' => null,
                'message' => $error,
                'httpStatusCode' => null
            ));
        }
        elseif (is_null(json_decode($output))) {
            // i.e. It does return a response but it is not in JSON format
            $output = json_encode(array(
                'errorCode' => 'non_json',
                'errorId' => null,
                'message' => curl_getinfo($ch, CURLINFO_CONTENT_TYPE) . ' response could not be decoded: ' . json_last_error_msg(),
                'httpStatusCode' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE)
            ));
        }

        curl_close($ch);

        return $output;
    }

}
