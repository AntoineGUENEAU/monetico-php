<?php

use Carbon\Carbon;
use DansMaCulotte\Monetico\Exceptions\Exception;
use DansMaCulotte\Monetico\Exceptions\CaptureException;
use DansMaCulotte\Monetico\Monetico;
use DansMaCulotte\Monetico\Requests\CaptureRequest;
use DansMaCulotte\Monetico\Resources\BillingAddressResource;
use DansMaCulotte\Monetico\Resources\CartItemResource;
use DansMaCulotte\Monetico\Resources\CartResource;
use DansMaCulotte\Monetico\Resources\ClientResource;
use DansMaCulotte\Monetico\Resources\ShippingAddressResource;
use PHPUnit\Framework\TestCase;

require_once 'Credentials.fake.php';

class CaptureRequestTest extends TestCase
{
    public function testPaymentConstruct()
    {
        $capture = new CaptureRequest([
            'reference' => 'ABCDEF123',
            'description' => 'PHPUnit',
            'language' => 'FR',
            'email' => 'john@english.fr',
            'amount' => 42.42,
            'currency' => 'EUR',
            'dateTime' => Carbon::create(2019, 1, 1),
            'successUrl' => 'https://127.0.0.1/success',
            'errorUrl' => 'https://127.0.0.1/error'
        ]);

        $this->assertTrue($capture instanceof CaptureRequest);
    }

    public function testPaymentUrl()
    {
        $url = CaptureRequest::getUrl();

        $this->assertTrue($url === 'https://p.monetico-services.com/paiement.cgi');

        $url = CaptureRequest::getUrl(true);

        $this->assertTrue($url === 'https://p.monetico-services.com/test/paiement.cgi');
    }

    public function testPaymentExceptionReference()
    {
        $this->expectExceptionObject(Exception::invalidReference('thisisabigerroryouknow'));

        new CaptureRequest([
            'reference' => 'thisisabigerroryouknow',
            'description' => 'PHPUnit',
            'language' => 'FR',
            'email' => 'john@english.fr',
            'amount' => 42.42,
            'currency' => 'EUR',
            'dateTime' => Carbon::create(2019, 1, 1),
            'successUrl' => 'https://127.0.0.1/success',
            'errorUrl' => 'https://127.0.0.1/error'
        ]);
    }

    public function testPaymentExceptionLanguage()
    {
        $this->expectExceptionObject(Exception::invalidLanguage('WTF'));

        new CaptureRequest([
            'reference' => 'ABCDEF123',
            'description' => 'PHPUnit',
            'language' => 'WTF',
            'email' => 'john@english.fr',
            'amount' => 42.42,
            'currency' => 'EUR',
            'dateTime' => Carbon::create(2019, 1, 1),
            'successUrl' => 'https://127.0.0.1/success',
            'errorUrl' => 'https://127.0.0.1/error'
        ]);
    }

    public function testPaymentExceptionDatetime()
    {
        $this->expectExceptionObject(Exception::invalidDatetime());

        new CaptureRequest([
            'reference' => 'ABCDEF123',
            'description' => 'PHPUnit',
            'language' => 'FR',
            'email' => 'john@english.fr',
            'amount' => 42.42,
            'currency' => 'EUR',
            'dateTime' => '42',
            'successUrl' => 'https://127.0.0.1/success',
            'errorUrl' => 'https://127.0.0.1/error'
        ]);
    }

    public function testPaymentOptions()
    {
        $capture = new CaptureRequest([
            'reference' => 'ABCDEF123',
            'description' => 'PHPUnit',
            'language' => 'FR',
            'email' => 'john@english.fr',
            'amount' => 42.42,
            'currency' => 'EUR',
            'dateTime' => Carbon::create(2019, 1, 1),
            'successUrl' => 'https://127.0.0.1/success',
            'errorUrl' => 'https://127.0.0.1/error'
        ]);

        $capture->setCardAlias('foobar');
        $this->assertArrayHasKey('aliascb', $capture->options);
        $this->assertTrue($capture->options['aliascb'] === 'foobar');

        $capture->setForceCard();
        $this->assertArrayHasKey('forcesaisiecb', $capture->options);
        $this->assertTrue($capture->options['forcesaisiecb'] === '1');

        $capture->setForceCard(false);
        $this->assertTrue($capture->options['forcesaisiecb'] === '0');

        $capture->setDisable3DS();
        $this->assertArrayHasKey('3dsdebrayable', $capture->options);
        $this->assertTrue($capture->options['3dsdebrayable'] === '1');

        $capture->setDisable3DS(false);
        $this->assertTrue($capture->options['3dsdebrayable'] === '0');

        $capture->setSignLabel('FooBar');
        $this->assertArrayHasKey('libelleMonetique', $capture->options);
        $this->assertTrue($capture->options['libelleMonetique'] === 'FooBar');

        $capture->setDisabledPaymentWays([
            '1euro',
            '3xcb',
            '4xcb',
            'fivory',
            'paypal'
        ]);
        $this->assertArrayHasKey('desactivemoyenpaiement', $capture->options);
        $this->assertTrue($capture->options['desactivemoyenpaiement'] === '1euro,3xcb,4xcb,fivory,paypal');

        $capture->setDisabledPaymentWays([
            '1euro',
            '3xcb',
            '4xcb',
            'fivory',
            'foobar'
        ]);
        $this->assertArrayHasKey('desactivemoyenpaiement', $capture->options);
        $this->assertTrue($capture->options['desactivemoyenpaiement'] === '1euro,3xcb,4xcb,fivory');
    }

    public function testPaymentCommitments()
    {
        $capture = new CaptureRequest(
            [
                'reference' => 'ABCDEF123',
                'description' => 'PHPUnit',
                'language' => 'FR',
                'email' => 'john@english.fr',
                'amount' => 200,
                'currency' => 'EUR',
                'dateTime' => Carbon::create(2019, 1, 1),
                'successUrl' => 'https://127.0.0.1/success',
                'errorUrl' => 'https://127.0.0.1/error'
            ],
            [
                [
                    'date' => '06/01/2019',
                    'amount' => 50,
                ],
                [
                    'date' => '12/01/2019',
                    'amount' => 100,
                ],
                [
                    'date' => '24/01/2019',
                    'amount' => 20,
                ],
                [
                    'date' => '02/02/2019',
                    'amount' => 30,
                ],
            ]
        );

        $seal = $capture->generateSeal(
            'FOO',
            []
        );

        $fields = $capture->generateFields(
            'FOO',
            $capture->fieldsToArray(
                'FOOBAR',
                3.0,
                'FOO'
            )
        );

        $this->assertIsArray($fields);
        $this->assertArrayHasKey('nbrech', $fields);
        $this->assertTrue($fields['nbrech'] === 4);

        $this->assertArrayHasKey('dateech1', $fields);
        $this->assertTrue($fields['dateech1'] === '06/01/2019');

        $this->assertArrayHasKey('montantech1', $fields);
        $this->assertTrue($fields['montantech1'] === '50EUR');

        $this->assertArrayHasKey('dateech2', $fields);
        $this->assertTrue($fields['dateech2'] === '12/01/2019');

        $this->assertArrayHasKey('montantech2', $fields);
        $this->assertTrue($fields['montantech2'] === '100EUR');

        $this->assertArrayHasKey('dateech3', $fields);
        $this->assertTrue($fields['dateech3'] === '24/01/2019');

        $this->assertArrayHasKey('montantech3', $fields);
        $this->assertTrue($fields['montantech3'] === '20EUR');

        $this->assertArrayHasKey('dateech4', $fields);
        $this->assertTrue($fields['dateech4'] === '02/02/2019');

        $this->assertArrayHasKey('montantech4', $fields);
        $this->assertTrue($fields['montantech4'] === '30EUR');
    }

    public function testSetOrderContext()
    {
        $capture = new CaptureRequest([
            'reference' => 'ABCDEF123',
            'description' => 'PHPUnit',
            'language' => 'FR',
            'email' => 'john@english.fr',
            'amount' => 42.42,
            'currency' => 'EUR',
            'dateTime' => Carbon::create(2019, 1, 1),
            'successUrl' => 'https://127.0.0.1/success',
            'errorUrl' => 'https://127.0.0.1/error'
        ]);

        $billingAddress = new BillingAddressResource('7 rue melingue', 'Caen', '14000', 'France');
        $capture->setBillingAddress($billingAddress);

        $shippingAddress = new ShippingAddressResource('7 rue melingue', 'Caen', '14000', 'France');
        $shippingAddress->setParameter('email', 'john@english.fr');
        $capture->setShippingAddress($shippingAddress);

        $client = new ClientResource();
        $client->setParameter('civility', 'MR');
        $client->setParameter('firstName', 'Foo');
        $client->setParameter('lastName', 'Boo');
        $capture->setClient($client);

        $cart = new CartResource();
        $item = new CartItemResource(10, 2);
        $item->setParameter('name', 'Pen');
        $cart->addItem($item);
        $capture->setCart($cart);

        $this->assertEquals('7 rue melingue', $capture->shippingAddress->getParameter('addressLine1'));
        $this->assertEquals('Caen', $capture->shippingAddress->getParameter('city'));
        $this->assertEquals('14000', $capture->shippingAddress->getParameter('postalCode'));
        $this->assertEquals('France', $capture->shippingAddress->getParameter('country'));
        $this->assertEquals('john@english.fr', $capture->shippingAddress->getParameter('email'));

        $this->assertEquals('7 rue melingue', $capture->billingAddress->getParameter('addressLine1'));
        $this->assertEquals('Caen', $capture->billingAddress->getParameter('city'));
        $this->assertEquals('14000', $capture->billingAddress->getParameter('postalCode'));
        $this->assertEquals('France', $capture->billingAddress->getParameter('country'));

        $this->assertEquals('MR', $capture->client->getParameter('civility'));
        $this->assertEquals('Foo', $capture->client->getParameter('firstName'));
        $this->assertEquals('Boo', $capture->client->getParameter('lastName'));
    }

    public function testSet3DSecure()
    {
        $capture = new CaptureRequest([
            'reference' => '12345679',
            'description' => 'PHPUnit',
            'language' => 'FR',
            'email' => 'john@english.fr',
            'amount' => 42.42,
            'currency' => 'EUR',
            'dateTime' => Carbon::create(2019, 07, 23),
            'successUrl' => 'https://127.0.0.1/success',
            'errorUrl' => 'https://127.0.0.1/error'
        ]);

        $capture->setThreeDSecureChallenge('challenge_mandated');
        $capture->setCardAlias('martin');
        $capture->setSignLabel('toto');

        $fields = $capture->fieldsToArray(
            EPT_CODE,
            '3.0',
            COMPANY_CODE
        );

        $seal = $capture->generateSeal(
            Monetico::getUsableKey(SECURITY_KEY),
            $fields
        );

        $fields = $capture->generateFields(
            $seal,
            $fields
        );

        $this->assertEquals($fields['ThreeDSecureChallenge'], 'challenge_mandated');
    }

    public function testPaymentException3DSecure()
    {
        $this->expectExceptionObject(CaptureException::invalidThreeDSecureChallenge('invalid_choice'));

        $capture = new CaptureRequest([
            'reference' => 'ABCDEF123',
            'description' => 'PHPUnit',
            'language' => 'FR',
            'email' => 'john@english.fr',
            'amount' => 42.42,
            'currency' => 'EUR',
            'dateTime' => Carbon::create(2019, 1, 1),
            'successUrl' => 'https://127.0.0.1/success',
            'errorUrl' => 'https://127.0.0.1/error'
        ]);

        $capture->setThreeDSecureChallenge('invalid_choice');
    }
}