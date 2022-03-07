<?php
/**
 * This file is part of POS plugin for FacturaScripts
 * Copyright (C) 2020 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */

namespace FacturaScripts\Plugins\POS\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Dinamic\Lib\POS\Sales\Customer;
use FacturaScripts\Dinamic\Lib\POS\Sales\Order;
use FacturaScripts\Dinamic\Lib\POS\Sales\OrderRequest;
use FacturaScripts\Dinamic\Lib\POS\Sales\PaymentStorage;
use FacturaScripts\Dinamic\Lib\POS\SalesSession;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\POS\Lib\POS\Sales\Product;
use FacturaScripts\Plugins\POS\Lib\POS\POSTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * POSpro Main Controller
 *
 * @author Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
class POSb extends Controller
{
    use POSTrait;

    /**
     * @var SalesSession
     */
    public $session;

    /**
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->setTemplate(false);

        $action = $this->request->request->get('action', '');
        $this->session = new SalesSession($this->user);


        if (false === $this->execAction($action)) {
            return;
        }

        $this->execAfterAction($action);
        $this->loadTemplate();
    }

    /**
     * Exec action before load all data.
     *
     * @param string $action
     * @return bool
     */
    protected function execAction(string $action): bool
    {
        switch ($action) {
            case 'search-barcode':
                $this->searchBarcode();
                return false;

            case 'search-customer':
                $this->searchCustomer();
                return false;

            case 'search-product':
                $this->searchProduct();
                return false;

            case 'recalculate-order':
                $this->recalculateOrder();
                return false;

            case 'save-new-customer':
                $this->saveNewCustomer();
                return false;

            case 'hold-order':
                $this->saveOrderOnHold();
                return false;

            case 'save-order':
                $this->saveOrder();
                return false;

            case 'get-orders-on-hold':
                $this->getOrdersOnHold();
                return false;

            case 'delete-order-on-hold':
                $this->deleteOrderOnHold();
                return false;

            case 'resume-order':
                $this->resumeOrder();
                return false;

            default:
                return true;
        }
    }

    /**
     * Exec action after load all data.
     *
     * @param string $action
     */
    protected function execAfterAction(string $action)
    {
        switch ($action) {
            case 'close-session':
                $this->closeSession();
                break;

            case 'open-session':
                $idterminal = $this->request->request->get('terminal', '');
                $amount = $this->request->request->get('saldoinicial', 0);
                $this->session->open($idterminal, $amount);
                break;

            case 'open-terminal':
                $idterminal = $this->request->request->get('terminal', '');
                $this->session->terminal($idterminal);
                break;

            case 'print-cashup':
                $this->printClosingVoucher();
                break;

            case 'change-user':
                $this->userLogin();
                break;

            default:
                break;
        }
    }

    /**
     * Search product by barcode.
     *
     * @return void
     */
    protected function searchBarcode()
    {
        $barcode = $this->request->request->get('query');

        $this->setResponse(Product::searchBarcode($barcode));
    }

    /**
     * Search customer by text.
     */
    protected function searchCustomer()
    {
        $customer = new Customer();
        $query = $this->request->request->get('query');

        $this->setResponse($customer->search($query));
    }

    /**
     * Set response by the given product search
     *
     * @return void
     */
    protected function searchProduct()
    {
        $query = $this->request->request->get('query', '');
        $tags = $this->request->request->get('tags', []);

        $this->setResponse(Product::search($query, $tags, $this->getTerminal()->codalmacen));
    }

    /**
     * @param array $data
     * @return void
     */
    protected function buildResponse(array $data = [])
    {
        $response = $data;
        $response['messages'] = $this->getMessages();
        $response['token'] = $this->token;

        $this->setResponse($response);
    }

    /**
     * Close current user POS session.
     */
    protected function closeSession()
    {
        $cash = $this->request->request->get('cash');
        $this->session->close($cash);

        $this->printClosingVoucher();
    }

    /**
     * Set a held order as complete to remove from list.
     */
    protected function deleteOrderOnHold()
    {
        $code = $this->request->request->get('code', '');

        if ($this->getStorage()->updateOrderOnHold($code)) {
            $this->toolBox()->i18nLog()->info('pos-order-on-hold-deleted');
        }

        $this->setNewToken();
        $this->buildResponse();
    }

    /**
     * Recalculate order data.
     *
     * @return void
     */
    protected function recalculateOrder()
    {
        $request = new OrderRequest($this->request);
        $order = new Order($request);

        $this->setResponse($order->recalculate(), false);
    }

    /**
     * Load order on hold by code.
     */
    protected function resumeOrder()
    {
        $code = $this->request->request->get('code', '');

        $this->setNewToken();
        $this->buildResponse($this->getStorage()->getOrderOnHold($code));
    }

    protected function saveNewCustomer()
    {
        $customer = new Customer();

        $taxID = $this->request->request->get('taxID');
        $name = $this->request->request->get('name');

        if ($customer->saveNew($taxID, $name)) {
            $this->setResponse($customer->getCustomer());
            return;
        }

        $this->setResponse('Error al guardar el cliente');
    }

    /**
     * Save order and payments.
     *
     * @return void
     */
    protected function saveOrder(): void
    {
        if (false === $this->validateRequest()) return;

        $orderRequest = new OrderRequest($this->request);
        $order = new Order($orderRequest);

        if ($this->getStorage()->placeOrder($order)) {
            $this->printVoucher($order->getDocument());
            $this->savePayments($orderRequest->getPaymentList());
            $this->toolBox()->i18nLog()->info('pos-order-ok');
        }

        $this->buildResponse();
    }

    /**
     * Put order on hold.
     *
     * @return void
     */
    protected function saveOrderOnHold(): void
    {
        if (false === $this->validateRequest()) return;

        $request = new OrderRequest($this->request, true);
        $order = new Order($request);

        if ($this->getStorage()->placeOrderOnHold($order)) {
            $this->toolBox()->i18nLog()->info('pos-order-on-hold');
        }

        $this->buildResponse();
    }

    protected function savePayments(array $payments)
    {
        $order = $this->getStorage()->getCurrentOrder();

        $storage = new PaymentStorage($order);
        $storage->savePayments($payments);
    }

    /**
     * Return some products for initial view
     *
     * @return array
     */
    public function getProducts(): array
    {
        return Product::search('', [], $this->getTerminal()->codalmacen);
    }

    /**
     * Return all families to show on quick acces bar.
     *
     * @return array
     */
    public function getCategories(): array
    {
        return (new Familia())->codeModelAll();
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'pos-b';
        $pagedata['menu'] = 'point-of-sale';
        $pagedata['icon'] = 'fas fa-shopping-cart';
        $pagedata['showonmenu'] = true;

        return $pagedata;
    }

    protected function getOrdersOnHold()
    {
         $this->setResponse($this->getStorage()->getOrdersOnHold());
    }
}
