<?php

namespace Mews\Pos\Tests\Gateways;

use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\CreditCardPosNet;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\PosNet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class PosNetTest extends TestCase
{
    /**
     * @var PosNetAccount
     */
    private $account;

    private $config;

    /**
     * @var CreditCardPosNet
     */
    private $card;
    private $order;
    /**
     * @var XmlEncoder
     */
    private $xmlDecoder;
    /**
     * @var PosNet
     */
    private $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../config/pos.php';

        $this->account = AccountFactory::createPosNetAccount('yapikredi', '6706598320', 'XXXXXX', 'XXXXXX', '67005551', '27426', '3d', '10,10,10,10,10,10,10,10');

        $this->card = new CreditCardPosNet('5555444433332222', '21', '12', '122', 'ahmet');

        $this->order = [
            'id'          => 'YKB_TST_190620093100_024',
            'name'        => 'siparis veren',
            'email'       => 'test@test.com',
            'amount'      => '1.75',
            'installment' => 0,
            'currency'    => 'TL',
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'rand'        => microtime(),
        ];

        $this->pos = PosFactory::createPosGateway($this->account);

        $this->pos->setTestMode(true);

        $this->xmlDecoder = new XmlEncoder();
    }

    public function testInit()
    {
        $this->assertEquals($this->config['banks'][$this->account->getBank()], $this->pos->getConfig());
        $this->assertEquals($this->account, $this->pos->getAccount());
        $this->assertNotEmpty($this->pos->getCurrencies());
    }

    public function testPrepare()
    {
        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals($this->card, $this->pos->getCard());
    }

    public function testCreate3DHash()
    {

        $this->pos->prepare($this->order, AbstractGateway::TX_PAY, $this->card);
        $this->assertEquals('J/7/Xprj7F/KDf98luVfIGyUPRQzUCqGwpmvz3KT7oQ=', $this->pos->create3DHash());
    }

    public function testVerifyResponseMAC()
    {

        $newOrder = $this->order;
        $newOrder['id'] = '895';
        $newOrder['amount'] = 1;
        $newOrder['currency'] = 'TL';

        $account = AccountFactory::createPosNetAccount('yapikredi', '6706598320', 'XXXXXX', 'XXXXXX', '67825768', '27426', '3d', '10,10,10,10,10,10,10,10');

        $pos = PosFactory::createPosGateway($account);
        $pos->setTestMode(true);

        $pos->prepare($newOrder, AbstractGateway::TX_PAY);
        $data = (object) [
            'mdStatus' => '9',
            'mac'      => 'U2kU/JWjclCvKZjILq8xBJUXhyB4DswKvN+pKfxl0u0=',
        ];
        $this->assertTrue($pos->verifyResponseMAC($data));

        $newOrder['id'] = '800';
        $pos->prepare($newOrder, AbstractGateway::TX_PAY);
        $data = (object) [
            'mdStatus' => '9',
            'mac'      => 'U2kU/JWjclCvKZjILq8xBJUXhyB4DswKvN+pKfxl0u0=',
        ];
        $this->assertFalse($pos->verifyResponseMAC($data));
    }

    public function testCreateRegularPaymentXML()
    {
        $order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '2',
            'currency'    => 'TRY',
        ];


        $card = new CreditCardPosNet('5555444433332222', '22', '01', '123', 'ahmet');
        /**
         * @var PosNet $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_PAY, $card);

        $actualXML = $pos->createRegularPaymentXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRegularPaymentXMLData($pos->getOrder(), $pos->getCard(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData['sale']);
    }

    public function testCreateRegularPostXML()
    {
        $order = [
            'id'           => '2020110828BC',
            'host_ref_num' => '019676067890000191',
            'amount'       => 10.02,
            'currency'     => 'TRY',
            'installment'  => '2',
        ];

        /**
         * @var PosNet $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_POST_PAY);

        $actualXML = $pos->createRegularPostXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRegularPostXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData['capt']);
    }

    public function testCreate3DPaymentXML()
    {

        $order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
        ];
        $responseData = [
            'BankPacket'     => 'F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
            'MerchantPacket' => 'E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
            'Sign'           => '9998F61E1D0C0FB6EC5203A748124F30',
        ];

        /**
         * @var PosNet $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actualXML = $pos->create3DPaymentXML($responseData);
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSample3DPaymentXMLData($pos->getAccount(), $responseData);
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData['oosTranData']);
    }

    public function testCreate3DResolveMerchantDataXML()
    {

        $order = [
            'id'          => '2020110828BC',
            'amount'      => 100.01,
            'installment' => '0',
            'currency'    => 'TRY',
        ];
        $responseData = [
            'BankPacket'     => 'F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
            'MerchantPacket' => 'E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F309998F61E1D0C0FB6EC5203A748124F30',
            'Sign'           => '9998F61E1D0C0FB6EC5203A748124F30',
        ];

        /**
         * @var PosNet $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_PAY);

        $actualXML = $pos->create3DResolveMerchantDataXML($responseData);
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleResolveMerchantDataXMLData($pos->getAccount(), $responseData);
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData['oosResolveMerchantData']);
    }

    public function testCreateStatusXML()
    {
        $order = [
            'id'   => '2020110828BC',
            'type' => 'status',
        ];

        /**
         * @var PosNet $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_STATUS);

        $actualXML = $pos->createStatusXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleStatusXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData['agreement']);
    }


    public function testCreateCancelXML()
    {
        $order = [
            'id'           => '2020110828BC',
            'host_ref_num' => '2020110828BCNUM',
        ];

        /**
         * @var PosNet $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_CANCEL);

        $actualXML = $pos->createCancelXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleCancelXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData['reverse']);
    }

    public function testCreateRefundXML()
    {
        $order = [
            'id'       => '2020110828BC',
            'amount'   => 50,
            'currency' => 'TRY',
        ];

        /**
         * @var PosNet $pos
         */
        $pos = PosFactory::createPosGateway($this->account);
        $pos->prepare($order, AbstractGateway::TX_REFUND);

        $actualXML = $pos->createRefundXML();
        $actualData = $this->xmlDecoder->decode($actualXML, 'xml');

        $expectedData = $this->getSampleRefundXMLData($pos->getOrder(), $pos->getAccount());
        $this->assertEquals($expectedData, $actualData);
        //$this->assertEquals([], $actualData['return']);
    }

    /**
     * @param                  $order
     * @param CreditCardPosNet $card
     * @param PosNetAccount    $account
     *
     * @return array
     */
    private function getSampleRegularPaymentXMLData($order, $card, $account)
    {
        return [
            'mid'              => $account->getClientId(),
            'tid'              => $account->getTerminalId(),
            'tranDateRequired' => '1',
            'sale'             => [
                'orderID'      => $order->id,
                'installment'  => $order->installment,
                'amount'       => $order->amount,
                'currencyCode' => $order->currency,
                'ccno'         => $card->getNumber(),
                'expDate'      => $card->getExpirationDate(),
                'cvc'          => $card->getCvv(),
            ],
        ];
    }

    /**
     * @param               $order
     * @param PosNetAccount $account
     *
     * @return array
     */
    private function getSampleRegularPostXMLData($order, $account)
    {
        return [
            'mid'              => $account->getClientId(),
            'tid'              => $account->getTerminalId(),
            'tranDateRequired' => '1',
            'capt'             => [
                'hostLogKey'   => $order->host_ref_num,
                'amount'       => $order->amount,
                'currencyCode' => $order->currency,
                'installment'  => $order->installment,
            ],
        ];
    }

    /**
     * @param PosNetAccount $account
     * @param array         $responseData
     *
     * @return array
     */
    private function getSample3DPaymentXMLData($account, array $responseData)
    {
        return [
            'mid'         => $account->getClientId(),
            'tid'         => $account->getTerminalId(),
            'oosTranData' => [
                'bankData'     => $responseData['BankPacket'],
                'merchantData' => $responseData['MerchantPacket'],
                'sign'         => $responseData['Sign'],
                'wpAmount'     => 0,
                'mac'          => 'oE7zwV87uOc2DFpGPlr4jQRQ0z9LsxGw56c7vaiZkTo=',
            ],
        ];
    }

    /**
     * @param PosNetAccount $account
     * @param array         $responseData
     *
     * @return array
     */
    private function getSampleResolveMerchantDataXMLData($account, array $responseData)
    {
        return [
            'mid'                    => $account->getClientId(),
            'tid'                    => $account->getTerminalId(),
            'oosResolveMerchantData' => [
                'bankData'     => $responseData['BankPacket'],
                'merchantData' => $responseData['MerchantPacket'],
                'sign'         => $responseData['Sign'],
                'mac'          => 'oE7zwV87uOc2DFpGPlr4jQRQ0z9LsxGw56c7vaiZkTo=',
            ],
        ];
    }

    /**
     * @param               $order
     * @param PosNetAccount $account
     *
     * @return array
     */
    private function getSampleStatusXMLData($order, $account)
    {
        return [
            'mid'       => $account->getClientId(),
            'tid'       => $account->getTerminalId(),
            'agreement' => [
                'orderID' => $order->id,
            ],
        ];
    }

    /**
     * @param               $order
     * @param PosNetAccount $account
     *
     * @return array
     */
    private function getSampleCancelXMLData($order, $account)
    {
        $requestData = [
            'mid'              => $account->getClientId(),
            'tid'              => $account->getTerminalId(),
            'tranDateRequired' => '1',
            'reverse'          => [
                'transaction' => 'sale',
            ],
        ];

        //either will work
        if (isset($order->host_ref_num)) {
            $requestData['reverse']['hostLogKey'] = $order->host_ref_num;
        } else {
            $requestData['reverse']['orderID'] = PosNet::mapOrderIdToPrefixedOrderId($order->id, $account->getModel());
        }

        return $requestData;
    }

    /**
     * @param               $order
     * @param PosNetAccount $account
     *
     * @return array
     */
    private function getSampleRefundXMLData($order, $account)
    {
        $requestData = [
            'mid'              => $account->getClientId(),
            'tid'              => $account->getTerminalId(),
            'tranDateRequired' => '1',
            'return'           => [
                'amount'       => $order->amount,
                'currencyCode' => $order->currency,
            ],
        ];

        if (isset($order->host_ref_num)) {
            $requestData['return']['hostLogKey'] = $order->host_ref_num;
        } else {
            $requestData['return']['orderID'] = $order->id;
        }

        return $requestData;
    }
}
