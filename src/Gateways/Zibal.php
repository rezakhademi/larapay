<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Redirect;

final class Zibal extends GatewayAbstract
{
    protected $url = 'https://gateway.zibal.ir/';

    protected $statuses = [
        '-1' => 'در انتظار پردخت',
        '-2' => 'خطای داخلی',
        '1' => 'پرداخت شده - تاییدشده',
        '2' => 'پرداخت شده - تاییدنشده',
        '3' => 'لغوشده توسط کاربر',
        '4' => '‌شماره کارت نامعتبر می‌باشد.',
        '5' => '‌موجودی حساب کافی نمی‌باشد.',
        '6' => 'رمز واردشده اشتباه می‌باشد.',
        '7' => '‌تعداد درخواست‌ها بیش از حد مجاز می‌باشد.',
        '8' => '‌تعداد پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد.',
        '9' => 'مبلغ پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد.',
        '10' => '‌صادرکننده‌ی کارت نامعتبر می‌باشد.',
        '11' => '‌خطای سوییچ',
        '12' => 'کارت قابل دسترسی نمی‌باشد.',
        '100' => 'با موفقیت تایید شد.',
        '102' => 'merchant یافت نشد.',
        '103' => 'merchant غیرفعال',
        '104' => 'merchant نامعتبر',
        '105' => 'amount بایستی بزرگتر از 1,000 ریال باشد.',
        '106' => 'callbackUrl نامعتبر می‌باشد. (شروع با http و یا https)',
        '113' => 'amount مبلغ تراکنش از سقف میزان تراکنش بیشتر است.',
        '201' => 'قبلا تایید شده',
        '202' => 'سفارش پرداخت نشده یا ناموفق بوده است.',
        '203' => 'trackId نامعتبر می‌باشد.',

        'token-mismatch' => 'عدم تطبیق توکن',
    ];

    protected $requirements = ['merchant'];

    public function request(
        int $id,
        int $amount,
        string $callbackUrl,
        string $nationalId,
        string $mobile
    ): array {
        $url = $this->url . 'v1/request';
        $params = [
            'merchant' => $this->config['merchant'],
            'amount' => $amount,
            'callbackUrl' => $callbackUrl,
            'description' => null,
            'orderId' => $id,
            'mobile' => null,
            'allowedCards' => null,
            'ledgerId' => null,
            'linkToPay' => null,
            'sms' => null,
        ];

        $result = $this->_request($url, $params);

        return [
            'token' => $result['trackId'],
            'fee' => $this->fee($amount),
        ];
    }

    public function redirect(int $id, string $token)
    {
        return Redirect::to($this->url . 'start/' . $token);
    }

    public function verify(
        int $id,
        int $amount,
        string $token,
        array $params = []
    ): array {
        $default = [
            'success' => null,
            'trackId' => null,
            'orderId' => null,
            'status' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['trackId'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }
        if ($params['success'] != 1) {
            throw new LarapayException($this->translateStatus($params['status']));
        }

        $url = $this->url . 'v1/verify';
        $data = [
            'merchant' => $this->config['merchant'],
            'trackId' => $token,
        ];
        $result = $this->_request($url, $data);

        return [
            'result' => $this->translateStatus($result['status']),
            'card' => $result['cardNumber'],
            'tracking_code' => $result['refNumber'],
            'reference_id' => $result['refNumber'],
            'fee' => $this->fee($amount),
        ];
    }

    private function _request(string $url, array $data)
    {
        $client = new Client;

        try {
            $response = $client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $data,
                    'timeout' => 10,
                ]
            );

            $result = json_decode($response->getBody(), true);

            if ($result['result'] != 100) {
                throw new LarapayException($this->translateStatus($result['result']));
            }

            return $result;
        } catch (BadResponseException $e) {
            throw new LarapayException($e->getMessage());
        }
    }
}
