<?php

namespace Netgsm\Otp;

use Exception;
use SimpleXMLElement;

class OTP
{   
   
    private $username;
    private $password;
    private $header;

    private const ERROR_MESSAGES = [
        20 => 'Mesaj metni ya da mesaj boyunu kontrol ediniz.',
        30 => 'Geçersiz kullanıcı adı , şifre veya kullanıcınızın API erişim izninin olmadığını gösterir. Ayrıca eğer API erişiminizde IP sınırlaması yaptıysanız ve sınırladığınız ip dışında gönderim sağlıyorsanız 30 hata kodunu alırsınız. API erişim izninizi veya IP sınırlamanızı, web arayüzden; sağ üst köşede bulunan ayarlar> API işlemleri menüsünden kontrol edebilirsiniz.',
        40 => 'Gönderici adınızı kontrol ediniz.',
        41 => 'Gönderici adınızı kontrol ediniz.',
        50 => 'Gönderilen numarayı kontrol ediniz.',
        60 => 'Hesabınızda OTP SMS Paketi tanımlı değildir, kontrol ediniz.',
        70 => 'Input parametrelerini kontrol ediniz.',
        80 => 'Sorgulama sınır aşımı.(dakikada 100 adet gönderim yapılabilir.)',
        100 => 'Sistem hatası.'
    ];

    public function __construct()
    {
        $this->setCredentials([
            'NETGSM_USERCODE' => 'username',
            'NETGSM_PASSWORD' => 'password',
            'NETGSM_HEADER'   => 'header',
        ]);
    }

    private function setCredentials(array $credentials)
    {
        foreach ($credentials as $envKey => $property) {
            if (!isset($_ENV[$envKey]) && $property !== 'header') {
                throw new \Exception("Environment variable {$envKey} not found.");
            }
            $this->$property = $_ENV[$envKey];
        }
    }

    /**
     * @param array{
     *      message: string,
     *      no: string,
     *      header:string
     * } $data
     */
    public function otp(array $data): array
    {
        $requiredFields = ['message', 'no'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return ['durum' => ucfirst($field) . ' giriniz'];
            }
        }

        $header = $data['header'] ?? $this->header;
        
        if (empty($header)) {
            throw new \Exception("Header cannot be empty.");
        }
    
        $xmlData = '<?xml version="1.0"?>
            <mainbody>
               <header>
                   <usercode>' . htmlspecialchars($this->username) . '</usercode>
                   <password>' . htmlspecialchars($this->password) . '</password>
                   <msgheader>' . htmlspecialchars($header) . '</msgheader>
               </header>
               <body>
                   <msg><![CDATA[' . htmlspecialchars($data['message']) . ']]></msg>
                   <no>' . htmlspecialchars($data['no']) . '</no>
               </body>
            </mainbody>';
    
        $ch = curl_init('https://api.netgsm.com.tr/sms/send/otp');
        curl_setopt_array($ch, [
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => ["Content-Type: text/xml"],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => $xmlData
        ]);
        
        $result = curl_exec($ch);

        if ($result === false) {
            return [
                'durum' => 'cURL hatası: ' . curl_error($ch),
                'status' => 'cURL hatası: ' . curl_error($ch)
            ];
        }
        curl_close($ch);
    
        $donen = new SimpleXMLElement($result);
        $code = (string)$donen->main->code;
    
        if (array_key_exists($code, self::ERROR_MESSAGES)) {
            return [
                'durum' => self::ERROR_MESSAGES[$code],
                'status' => self::ERROR_MESSAGES[$code],
                'code' => $code
            ];
        }
    
        return [
            'durum' => 'Gönderim başarılı.',
            'status' => 'Successfully sent.',
            'jobid' => (string)$donen->main->jobID[0]
        ];
    }
}
