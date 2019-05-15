<?php
/**
 * @package       ICEPAY Magento 2 Payment Module
 * @copyright     (c) 2016-2018 ICEPAY. All rights reserved.
 * @license       BSD 2 License, see LICENSE.md
 */

namespace Icepay\IcpCore\Model\ConfigProvider;

class CreditCard extends AbstractConfigProvider
{
    /**
     * @var string[]
     */
    protected $methodCode = \Icepay\IcpCore\Model\PaymentMethod\CreditCard::CODE;

}
