<?php
/** 
 * Author: Alex Babiev 
 * https://github.com/iamguruman/modulbank 
 */

namespace app\models;

use yii\base\Model;
use yii\helpers\Url;

/**
 * Модель денежной транзакции
 * Публичный метод loadTransaction() используется для получения
 * данных транзакции с сервера Модульбанка
 */
class ModulbankTransaction extends Model
{
    /**
     * @var $secret_key - секретный ключ из личного кабинета
     * Обязательный параметр.
     */
    protected $secret_key;

    /**
     * @var $transaction_id - Идентификатор транзакции
     * Обязательный параметр.
     * Строка (максимум 50 символов).
     */
    protected $transactionId;

    /**
     * @var $merchant - Идентификатор магазина, который выдается в личном кабинете на этапе интеграции.
     * Обязательный параметр.
     * Строка (максимум 128 символов).
     * Допускаются только печатные
     * ASCII символы.
     */
    protected $merchant;

    /**
     * @var int $unix_timestamp - unix_timestamp
     * Обязательный параметр.
     * Дата и время.
     * Формат: UNIX Time.
     */
    protected $unix_timestamp;

    /**
     * @var string $salt - Случайная величина.
     * Необязательный параметр.
     * Строка (максимум 32 символа)
     * Допускаются только печатные
     * ASCII символы.
     */
    protected $salt;

    public $status;
    public $amount;
    public $auth_code;
    public $auth_number;
    public $client_email;
    public $client_phone;
    public $completed_datetime;
    public $created_datetime;
    public $currency;
    public $custom_order_id;
    public $description;
    public $message;
    public $meta;
    public $mps_error_code;
    public $order_id;
    public $original_amount;
    public $pan_mask;
    public $payment_method;
    public $refunds;
    public $rrn;

    /**
     * @var $state
     * Возможные значения поля state:
     * PROCESSING - В процессе
     * WAITING_FOR_3DS - Ожидает 3DS
     * FAILED - Ошибка
     * COMPLETE - Готово
     */
    public $state;

    public $testing;
    public $transaction_id;
    public $updated_datetime;

    /**
     * загружаю транзакцию из банка
     * @param string $transactionId - номер транзакции
     * @param string $secret_key - секретный ключ (взять в лк или в тех поддержке банка)
     * @param string $merchant - номер мерчанта (взять из лк или в техподдержке банка)
     * Пример запроса:
     * https://pay.modulbank.ru/api/v1/transaction/?
     *  transaction_id=qo9Kjd1vW68Pn1h9g2173e
     *  &merchant=ad25ef06-1824-413f-8ef1-c08115b9b979
     *  &unix_timestamp=1542080393
     *  &signature=b47025989516768fc1fc60a5b38ab1d5cc3a8fbf
     *  &salt=GfudKOAsXobWVpNovJHCreKmJXNkLqtf
     */
    public function loadTranscation(string $transactionId, string $merchant, string $secret_key)
    {
        $this->secret_key = $secret_key;
        $this->merchant = $merchant;
        $this->unix_timestamp = time();

        $salt = self::aGenerateRandomString();

        $array = [
            'transaction_id' => $transactionId,
            'merchant' => $merchant,
            'unix_timestamp' => $this->unix_timestamp,
            'salt' => $salt,
        ];

        $signature = $this->get_signature($array, $secret_key);

        $url = [
            "https://pay.modulbank.ru/api/v1/transaction/",
                'transaction_id' => $transactionId,
                'merchant' => $merchant,
                'unix_timestamp' => $this->unix_timestamp,
                'signature' => $signature,
                'salt' => $salt
        ];

        $url = Url::to($url);
        $url = str_replace("/https://ru/", "https://", $url);
        $url = str_replace("/https://en/", "https://", $url);
        $result = file_get_contents($url);

        ddd($result);

    }

    /**
     * Двойное шифрование sha1 на основе секретного ключа
     * подставьте ваш секретный ключ вместо 00112233445566778899aabbccddeeff
     */
    protected static function double_sha1($data, $secret_key) {
        for ($i = 0; $i < 2; $i++) {
            //$data = sha1('00112233445566778899aabbccddeeff' . $data);
            $data = sha1($secret_key. $data);
        }
        return $data;
    }

    /**
     * Вычисляем подпись (signature). Подпись считается на основе склеенной строки из
     * отсортированного массива параметров, исключая из расчета пустые поля и элемент "signature"
     */
    protected function get_signature(array $params, $key = 'signature') {
        $keys = array_keys($params);
        sort($keys);
        $chunks = array();
        foreach ($keys as $k) {
            $v = (string) $params[$k];
            if (($v !== '') && ($k != 'signature') && ($k != 'secret_key')) {
                $chunks[] = $k . '=' . base64_encode($v);
            }
        }
        $data = implode('&', $chunks);

        //echo 'data: '.$data."\n";
        $sig = self::double_sha1($data, $this->secret_key);

        return $sig;
    }

    protected static function aGenerateRandomString($lenght = 32,
                                                    $characters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"){
        $charactersLength = strlen($characters);
        $string = '';
        for ($i = 0; $i < 32; $i++) {
            $string .= $characters[rand(0, $charactersLength - 1)];
        }

        return $string;
    }


}
