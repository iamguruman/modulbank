<?php
/**
 * Author: Alex Babiev
 * https://github.com/iamguruman/modulbank
 */

namespace app\models;

use ReflectionClass;
use ReflectionProperty;
use yii\base\Model;

/**
 * модель платежа через банк Модульбанк
 * составлено 2022-09-18 на основании: https://modulbank.ru/support/formation_payment_request
 */
class ModulbankPay extends Model
{

    public function __construct(
        $merchant,
        $secret_key,
        $amount,
        $order_id,
        $description,
        $success_url,
        $receipt_items = '',
        $client_phone = null,
        $client_email = null,
        $receipt_contact = null
    )
    {
        $this->merchant = $merchant;
        $this->secret_key = $secret_key;
        $this->amount = $amount;
        $this->order_id = $order_id;
        $this->description = $description;
        $this->success_url = $success_url;

        if(!empty($receipt_items)){
            $this->receipt_items = json_encode($receipt_items);
        }

        $this->client_phone = $client_phone;
        $this->client_email = $client_email;
        $this->receipt_contact = $receipt_contact;

        $this->salt = self::aGenerateRandomString();

        $this->unix_timestamp = time();

    }

    public function getAttributes($names = null, $except = [])
    {
        $reflect = new ReflectionClass($this);
        $props   = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);

        foreach ($props as $prop) {
            $valname = $prop->getName();

            if($valname == 'signature'){ continue; }
            if($valname == 'secret_key'){ continue; }

            if(!empty($this->$valname)){
                $array [$prop->getName()] = $this->$valname;
            }

            //ddd($this->$valname);

            //ddd($prop->getValue());
            //ddd($prop->getName());
            //print $prop->getName() . "\n";

            //$array [$prop->getName()] = $prop->getValue();
        }

        $array ['signature'] = self::get_signature($array, $this->secret_key);

        return $array;
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

    protected $secret_key;

    // Для проведения платежа необходимо отправить POST-запросом форму со следующими полями:

    /** @var string $merchant - Идентификатор магазина, полученный в ЛК банка
     * Обязательность - да
     * – Строка (максимум 128 символов)
     * – Только печатные ASCII символы
     * пример использования:
     *      <input type="hidden" name="merchant" value="ad25ef06-1824-413f-8ef1-c08115b9b979">
     */
    public $merchant;

    /** @var float $amount - Сумма платежа
     * Обязательность - да
     * – Вещественное число, два знака после точки
     * – Формат: «100» или «200.45»
     * пример использования:
     *      <input type="hidden" name="amount" value="973">
     */
    public $amount;

    /** @var string $order_id - Уникальный идентификатор заказа в интернет-магазине
     * Обязательность - да
     * – Строка (максимум 50 символов)
     * пример использования:
     *      <input type="hidden" name="order_id" value="14425840">
     */
    public $order_id;

    /** @var string $custom_order_id - Идентификатор заказа, который будет отображаться покупателю
     * Обязательность - нет
     * – Строка (максимум 50 символов)
     */
    public $custom_order_id;

    /** @var string $description - Описание платежа
     * Обязательность - да
     * – Строка (максимум 250 символов)
     * пример использования:
     *      <input type="hidden" name="description" value="Заказ №14425840">
     */
    public $description;

    /**
     * @var string $success_url - Адрес для переадресации плательщика в случае успешной оплаты
     * Обязательность - да
     * – Строка (максимум 128 символов)
     * Если хотите использовать страницу успешной оплаты Модульбанка,
     * то укажите в этом поле: https://pay.modulbank.ru/success
     */
    public $success_url;

    /**
     * @var int $testing - Оплаты в тестовом режиме. Кол-во не ограничено. Кассовые чеки не формируются
     * Обязательность - нет
     * – Значение логического типа:
     *          1 — тестовый платеж,
     *          0 — реальный платеж
     * – По умолчанию реальные платежи
     */
    public $testing;

    /**
     * @var string $callback_url - Адрес для уведомлений в случае успешной оплаты
     * Обязательность - нет
     * – Строка (максимум 128 символов)
     * пример использования:
     *      <input type="hidden" name="testing" value="1">
     */
    public $callback_url;

    /**
     * @var int $callback_on_failure - Отправка уведомления при неуспешной оплате
     * Обязательность - нет
     * – Значение логического типа:
     *      1 — отправлять,
     *      0 — не отправлять
     * – По умолчанию не отправляется
     */
    public $callback_on_failure;

    /**
     * @var string $client_phone - Номер телефона клиента
     * Обязательность - нет
     * – Строка (максимум 15 символов)
     * – Формат: «+75559091555»
     * пример использования:
     *      <input type="hidden" name="client_phone" value="+7 912 9876543">
     */
    public $client_phone;

    /**
     * @var string $client_name - Имя и фамилия клиента
     * Обязательность - нет
     * – Строка (максимум 100 символов
     */
    public $client_name;

    /**
     * @var string $client_email - E-mail клиента
     * Обязательность - нет
     * – Строка (максимум 64 символа)
     * пример использования:
     *      <input type="hidden" name="client_email" value="test@test.ru">
     */
    public $client_email;

    /**
     * @var string $client_id - Идентификатор клиента
     * Обязательность - нет
     * – Строка (максимум 128 символов)
     * – Только печатные ASCII символы
     */
    public $client_id;

    /**
     * @var string $meta - Поле для дополнительных параметров в формате JSON
     * Обязательность - нет
     * – JSON-строка
     */
    public $meta;

    /**
     * @var string $receipt_contact - Еmail получателя чека. Если в ЛК включена удаленная регистрация чеков через
     *                          онлайн-кассу, на этот адрес отправится чек
     * Обязательность - нет
     * – Строка (максимум 64 символа)
     * пример использования:
     *      <input type="hidden" name="receipt_contact" value="test@mail.com">
     */
    public $receipt_contact;

    /**
     * @var string $receipt_items - Позиции чека (json-объект с позициями чека)
     * Обязательность - Да(если в ЛК включена удаленная регистрация чеков через онлайн-кассу)
     * – Обязательные поля и пример json-объекта описаны в разделе "Отправка чеков"
     * пример использования:
     *  <input
     *      type="hidden"
     *      name="receipt_items"
            value="[{&quot;discount_sum&quot;: 40, &quot;name&quot;: &quot;Товар 1&quot;, &quot;payment_method&quot;: &quot;full_prepayment&quot;, &quot;payment_object&quot;: &quot;commodity&quot;, &quot;price&quot;: 48, &quot;quantity&quot;: 10, &quot;sno&quot;: &quot;osn&quot;, &quot;vat&quot;: &quot;vat10&quot;}, {&quot;name&quot;: &quot;Товар 2&quot;, &quot;payment_method&quot;: &quot;full_prepayment&quot;, &quot;payment_object&quot;: &quot;commodity&quot;, &quot;price&quot;: 533, &quot;quantity&quot;: 1, &quot;sno&quot;: &quot;osn&quot;, &quot;vat&quot;: &quot;vat10&quot;}]"
     *  >
     */
    public $receipt_items;

    /**
     * @var integer $unix_timestamp - Текущее время
     * Обязательность - да
     * – Дата и время
     * – Формат: UNIX Time
     * пример использования:
     *      <input type="hidden" name="unix_timestamp" value="1573451160">
     */
    public $unix_timestamp;

    /**
     * @var integer $lifetime - Время жизни страницы в секундах
     * Обязательность - нет
     * – Целое число
     */
    public $lifetime;

    /**
     * @var string $timeout_url - Адрес для переадресации плательщика по истечении времени, указанного в lifetime
     * Обязательность - нет
     * – Строка (максимум 128 символов)
     */
    public $timeout_url;

    /**
     * @var string $salt - Случайная величина
     * Обязательность - нет
     * – Строка (максимум 32 символа)
     * – Только печатные ASCII символы
     * пример использования:
     *      <input type="hidden" name="salt" value="dPUTLtbMfcTGzkaBnGtseKlcQymCLrYI">
     */
    public $salt;

    /**
     * @var string $signature - Криптографическая подпись
     * Обязательность - нет
     * – Строка (40 символов в нижнем регистре)
     * – Алгоритм вычисления вычисления подписи описан в разделе «Алгоритм вычисления поля signature»
     *   ( https://modulbank.ru/support/algorithm_for_calculating_signature_field )
     * пример использвания:
     *      <input type="hidden" name="signature" value="9b28fa592922dc8a0c1ba2e40f2c0432aa617afd">
     */
    public $signature;

    /**
     * @var int $start_recurrent - Указывает, что платёж будет рекуррентный. Подробнее в разделе "Рекуррентные платежи"
     * Обязательность - нет
     * – Значение логического типа
     *          1 — рекуррентная транзакция,
     *          0 или отсутствует — не рекуррентная транзакция
     * – По умолчанию не отправляется
     */
    public $start_recurrent;

    /**
     * @var int $preauth - Указывает, что оплата будет двухстадийной. Подробнее в разделе"Холдирование"
     * Обязательность - нет
     * – Значение логического типа
     *      • 1— запрос с холдированием,
     *      • 0 или отсутствует— одностадийный платёж
     * – По умолчанию не отправляется
     */
    public $preauth;

    /**
     * @var string $show_payment_methods - Указывает, какие из доступных способов оплаты показывать
     *                              покупателю на платёжной странице
     * Обязательность - нет
     * – Строка
     * – Доступные значения:
     *      • card — карты,
     *      • sbp — СБП,
     *      • yandexpay — кнопка YandexPay
     * – По умолчанию выводятся все подключенные методы.
     * Можно передавать один: ["sbp"] или несколько: ["sbp","card"].
     * YandexPay – используется только в связке с другим способом.
     */
    public $show_payment_methods;

    /**
     * @var int $callback_with_receipt - Добавляет в коллбек об оплате информацию о сформированных чеках для онлайн-кассы
     * Обязательность - нет
     * – По умолчанию не отправляется
     * – Значение логического типа
     *      • 1 — отправлять информацию,
     *      • 0 или отсутствует — не отправлять
     */
    public $callback_with_receipt;

    public function rules()
    {
        return [
            [['merchant'], 'required'],
            [['merchant'], 'string', 'max' => 128],

            [['amount'], 'required'],
            [['amount'], 'number'],

            [['order_id'], 'required'],
            [['order_id'], 'string', 'max' => 50],

            [['custom_order_id'], 'string', 'max' => 50],

            [['description'], 'required'],
            [['description'], 'string', 'max' => 250],

            [['success_url'], 'required'],
            [['success_url'], 'string', 'max' => 128],

            [['testing'], 'integer', 'max' => 1],

            [['callback_url'], 'string', 'max' => 128],

            [['callback_on_failure'], 'integer', 'max' => 1],

            [['client_phone'], 'string', 'max' => 15],

            [['client_name'], 'string', 'max' => 100],

            [['client_email'], 'string', 'max' => 64],

            [['client_id'], 'string', 'max' => 128],

            [['meta'], 'string'],

            [['receipt_contact'], 'string', 'max' => 64],

            //[['receipt_items'], 'required'],
            [['receipt_items'], 'string'],

            [['unix_timestamp'], 'required'],
            [['unix_timestamp'], 'integer'],

            [['lifetime'], 'integer'],

            [['timeout_url'], 'string', 'max' => 128],

            [['salt'], 'string', 'max' => 32],

            [['signature'], 'required'],
            [['signature'], 'string', 'max' => 40],

            [['start_recurrent'], 'integer', 'max' => 1],
            [['preauth'], 'integer', 'max' => 1],

            [['show_payment_methods'], 'string', 'max' => 20],

            [['callback_with_receipt'], 'integer', 'max' => 1],


        ];
    }

}
