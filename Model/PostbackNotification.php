<?php


namespace Icepay\IcpCore\Model;

//TODO: replace
require_once(dirname(__FILE__) . '/restapi/src/Icepay/API/Autoloader.php');
use Icepay\IcpCore\Api\PostbackNotificationInterface;
use Magento\Store\Model\ScopeInterface;
use Icepay_StatusCode;
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\NoSuchEntityException;


class PostbackNotification implements PostbackNotificationInterface
{


    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var Icepay_Postback
     */
    protected $icepayPostback;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var \Magento\Sales\Model\Order $order
     */
    protected $order;

    /**
     * @var \Magento\Framework\Webapi\Request $request
     */
    public $request;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Framework\Webapi\Request $request
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\Webapi\Request $request,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        LoggerInterface $logger
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->order = $order;
        $this->request = $request;
        $this->objectManager = $objectManager;
        $this->logger = $logger;

    }


    public function processGet()
    {
            return "success";
    }

    public function processPostbackNotification()
    {

        try {

            $this->logger->debug("*******[ICEPAY] Postback\Notification*******");
            $this->logger->debug('request => ' . print_r($this->request, true));

            $orderID = preg_replace('/[^a-zA-Z0-9_\s]/', '', strip_tags($this->request->getParam('OrderID')));

            $this->order->loadByIncrementId($orderID);

            if (!$this->order->getId()) {
                $this->logger->debug(sprintf('Order %s not found!', $orderID));

                //throw NoSuchEntityException::singleField('orderID', $orderID);
                throw new \Magento\Framework\Webapi\Exception(
                    sprintf(__('Order %s not found!'), $orderID),
                    0,
                    \Magento\Framework\Webapi\Exception::HTTP_NOT_FOUND
                );
            };

            if (!$this->initIcepayPostback($this->order->getStore())) {
                $this->logger->debug(sprintf('Postback inicialization\validation failed.  %s ', print_r($this->request->getPost(), true)));

                throw new \Magento\Framework\Webapi\Exception(
                    __('Postback inicialization\validation failed.'),
                    0,
                    \Magento\Framework\Webapi\Exception::HTTP_UNAUTHORIZED
                );
            }

            $this->order->loadByIncrementId($this->icepayPostback->getOrderID());

            switch ($this->icepayPostback->getStatus()) {
                case Icepay_StatusCode::OPEN:
                    $this->order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
                    $this->order->setStatus('icepay_icpcore_open');
                    $this->order->setIsNotified(false);
                    $this->order->save();
                    break;
                case Icepay_StatusCode::SUCCESS:
                    $this->order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    $this->order->setStatus('icepay_icpcore_ok');
                    $this->order->save();
//                    $this->order->setIsNotified(false);
                    break;
                case Icepay_StatusCode::ERROR:
                    $this->order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
                    $this->order->setStatus('icepay_icpcore_error');

                    if ($this->order->canCancel()) {
                        $this->order->cancel();
                        $this->order->setStatus('canceled');
                        $this->order->save();
                    }

                    break;
            }

        }
        catch (\Magento\Framework\Webapi\Exception $e)
        {
            $this->logger->error($e->getMessage());
            throw $e;
        }
        catch (\Exception $e) {
            $this->logger->critical($e);

            throw new \Magento\Framework\Webapi\Exception(
                __('Internal Error'),
                0,
                \Magento\Framework\Webapi\Exception::HTTP_INTERNAL_ERROR
            );
        }
    }


    /**
     * Init Icepay_Result object
     *
     * @param Icepay_Result $icepayResult
     */
    public function initIcepayPostback($store)
    {

        $icepayPostback = $this->objectManager->create('Icepay_Postback');

        $merchantId = $this->scopeConfig->getValue('payment/icepay_settings/merchant_id', ScopeInterface::SCOPE_STORE, $store);
        $secretCode = $this->scopeConfig->getValue('payment/icepay_settings/merchant_secret', ScopeInterface::SCOPE_STORE, $store);
        $secretCode = $this->encryptor->decrypt($secretCode);

        $postback = $icepayPostback->setMerchantID($merchantId)->setSecretCode($secretCode);

        if ($postback->validate()) {

            $this->icepayPostback = $postback;
            return true;
        }
        return false;

    }

}