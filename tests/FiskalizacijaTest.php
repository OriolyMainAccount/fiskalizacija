<?php

use Carbon\Carbon;
use Nticaric\Fiskalizacija\Bill\Bill;
use Nticaric\Fiskalizacija\Bill\BillNumber;
use Nticaric\Fiskalizacija\Bill\BillRequest;
use Nticaric\Fiskalizacija\Bill\Refund;
use Nticaric\Fiskalizacija\Bill\TaxRate;
use Nticaric\Fiskalizacija\Business\Address;
use Nticaric\Fiskalizacija\Business\AddressData;
use Nticaric\Fiskalizacija\Business\BusinessArea;
use Nticaric\Fiskalizacija\Business\BusinessAreaRequest;
use Nticaric\Fiskalizacija\Fiskalizacija;

class FiskalizacijaTest extends \PHPUnit_Framework_TestCase
{
    public function config()
    {
        return [
            'certificatePath' => "./path/to/demo.pfx",
            'password'        => "password",
        ];
    }

    public function mockFiskalizacijaClass()
    {
        $mock = $this->getMockBuilder('Nticaric\Fiskalizacija\Fiskalizacija')
            ->setMethods(['readCertificateFromDisk', 'signXML', 'sendSoap', 'getPrivateKey'])
            ->setConstructorArgs($this->config())
            ->getMock();

        $keyPair = openssl_pkey_new([
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($keyPair, $privateKeyPem);

        $mock->method('getPrivateKey')
            ->willReturn($privateKeyPem);

        return $mock;
    }

    public function testSetCertificate()
    {
        $fis              = $this->mockFiskalizacijaClass();
        $fis->certificate = "certificate";
        $pathToDemoCert   = "./tests/demo.pfx";
        $fis->setCertificate($pathToDemoCert, "password");
        $this->assertNotNull($fis->certificate, 'Certificate must not be null');
    }

    public function testSetCertificateWithWrongpassword()
    {
        $config = $this->config();
        $fis    = $this->mockFiskalizacijaClass();
        $this->assertNull($fis->certificate, 'Certificate must not be null');
    }

    public function testSignXML()
    {
        $config              = $this->config();
        $businessAreaRequest = $this->setBusinessAreaRequest();

        $fis = $this->mockFiskalizacijaClass();
        $fis->expects($this->once())
            ->method('signXML')
            ->will($this->returnArgument(0));

        $soapMessage = $fis->signXML($businessAreaRequest->toXML());

        $this->assertNotNull($soapMessage);

    }

    public function testSendSoapBusinessRequest()
    {
        $config              = $this->config();
        $businessAreaRequest = $this->setBusinessAreaRequest();

        $fis = $this->mockFiskalizacijaClass();
        $fis->expects($this->once())
            ->method('signXML')
            ->will($this->returnArgument(0));
        $fis->expects($this->once())
            ->method('sendSoap')
            ->will($this->returnValue('PoslovniProstorOdgovor'));
        $soapMessage = $fis->signXML($businessAreaRequest->toXML());

        $res = $fis->sendSoap($soapMessage);
        $this->assertContains('PoslovniProstorOdgovor', $res);
    }

    public function testSendSoapBillRequest()
    {
        $config      = $this->config();
        $billRequest = $this->setBillRequest();

        $fis = $this->mockFiskalizacijaClass();
        $fis->expects($this->once())
            ->method('signXML')
            ->will($this->returnArgument(0));

        $fis->expects($this->once())
            ->method('sendSoap')
            ->will($this->returnValue('RacunOdgovor'));

        $soapMessage = $fis->signXML($billRequest->toXML());

        $res = $fis->sendSoap($soapMessage);
        $this->assertContains('RacunOdgovor', $res);
    }

    public function setBillRequest()
    {
        $refund = new Refund("Naziv naknade", 5.44);

        $billNumber = new BillNumber(1, "ODV1", "1");

        $istPdv    = [];
        $listPdv[] = new TaxRate(25.1, 400.1, 20.1, null);
        $listPdv[] = new TaxRate(10.1, 500.1, 15.444, null);

        $listPnp   = [];
        $listPnp[] = new TaxRate(30.1, 100.1, 10.1, null);
        $listPnp[] = new TaxRate(20.1, 200.1, 20.1, null);

        $listOtherTaxRate   = [];
        $listOtherTaxRate[] = new TaxRate(40.1, 453.3, 12.1, "Naziv1");
        $listOtherTaxRate[] = new TaxRate(27.1, 445.1, 50.1, "Naziv2");

        $bill = new Bill();

        $bill->setOib("32314900695");
        $bill->setHavePDV(true);
        $bill->setDateTime("15.07.2014T20:00:00");
        //  $bill->setNoteOfOrder("P");
        $bill->setBillNumber($billNumber);
        $bill->setListPDV($listPdv);
        $bill->setListPNP($listPnp);
        $bill->setListOtherTaxRate($listOtherTaxRate);
        $bill->setTaxFreeValue(23.5);
        $bill->setMarginForTaxRate(32.0);
        $bill->setTaxFree(5.1);
        //$bill->setRefund(refund);
        $bill->setTotalValue(456.1);
        $bill->setTypeOfPlacanje("G");
        $bill->setOibOperative("34562123431");

        $fis = $this->mockFiskalizacijaClass();

        $bill->setSecurityCode(
            $bill->securityCode(
                $fis->getPrivateKey(),
                $bill->oib,
                $bill->dateTime,
                $billNumber->numberNoteBill,
                $billNumber->noteOfBusinessArea,
                $billNumber->noteOfExcangeDevice,
                $bill->totalValue
            )
        );
        $bill->setNoteOfRedelivary(false);

        $billRequest = new BillRequest($bill);
        return $billRequest;
    }

    public function setBusinessAreaRequest()
    {
        $address              = new Address;
        $address->street      = "Sv. Mateja";
        $address->houseNumber = "19";
        $address->zipCode     = "10000";
        $address->settlement  = "Zagreb";
        $address->city        = "Zagreb";

        $addressData = new AddressData;
        $addressData->setAddress($address);

        $businessArea = new BusinessArea;
        $businessArea->setAddressData($addressData);

        $date = Carbon::now()->format("d.m.Y");
        $businessArea->setDateOfusage($date);

        $businessArea->setNoteOfBusinessArea("ODV1");
        //$businessArea->setNoteOfClosing("Z");
        $businessArea->setOib("32314900695");
        $businessArea->setSpecificPurpose("spec namjena");

        $businessArea->setWorkingTime("Pon:08-11h Uto:15-17");
        $businessAreaRequest = new BusinessAreaRequest($businessArea);

        return $businessAreaRequest;
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Ne mogu procitati certifikat sa lokacije:
     */
    public function testReadCertificateFromDiskException()
    {
        $fis = $this->getMockBuilder('Nticaric\Fiskalizacija\Fiskalizacija')
            ->setMethods(null)
            ->setConstructorArgs($this->config())
            ->getMock();
    }
}
