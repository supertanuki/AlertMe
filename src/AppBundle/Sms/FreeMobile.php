<?php

namespace AppBundle\Sms;

class FreeMobile
{
    protected $url = "https://smsapi.free-mobile.fr/sendmsg";
    protected $user;
    protected $key;
    protected $curl;

    public function __construct($key, $user)
    {
        $this->key  = $key;
        $this->user = $user;
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
    }

    public function __destruct()
    {
        curl_close($this->curl);
    }

    /**
     * @param string $msg
     *
     * @return $this
     * @throws \Exception
     */
    public function send($msg)
    {
        $msg = trim($msg);
        if (!$this->user || !$this->key || empty($msg)) {
            throw new \Exception("Un des paramètres obligatoires est manquant", 400);
        }
        curl_setopt(
            $this->curl,
            CURLOPT_URL,
            $this->url . "?user=" . $this->user .
            "&pass=" . $this->key .
            "&msg=" . urlencode($msg)
        );
        curl_exec($this->curl);
        if (200 != $code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE)) {
            switch ($code) {
                case 400:
                    $message = "Un des paramètres obligatoires est manquant.";
                    break;
                case 402:
                    $message = "Trop de SMS ont été envoyés en trop peu de temps.";
                    break;
                case 403:
                    $message = "Vous n'avez pas activé la notification SMS dans votre espace abonné Free Mobile ou votre identifiant/clé est incorrect.";
                    break;
                case 500:
                    $message = "erreur sur serveur Free Mobile.";
                    break;
                default:
                    $message = "erreur inconnue.";
            }
            throw new \Exception($message, $code);
        }

        return $this;
    }
}
