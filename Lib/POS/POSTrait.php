<?php

namespace FacturaScripts\Plugins\POS\Lib\POS;

use FacturaScripts\Dinamic\Lib\POS\Printer;
use FacturaScripts\Dinamic\Lib\POS\Sales\OrderStorage;
use FacturaScripts\Dinamic\Lib\POS\SalesDataGrid;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\DenominacionMoneda;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\TerminalPuntoVenta;
use FacturaScripts\Dinamic\Model\User;
use Symfony\Component\HttpFoundation\Cookie;

trait POSTrait
{
    /**
     * @var string
     */
    protected $token;

    /**
     * Returns the cash payment method ID.
     *
     * @return string
     */
    public function getCashPaymentMethod(): ?string
    {
        return $this->getSetting('fpagoefectivo');
    }

    /**
     * @return Cliente
     */
    public function getCustomer(): Cliente
    {
        return new Cliente();
    }

    public function getDefaultDocument()
    {
        return $this->getTerminal()->defaultdoc ?: $this->getSetting('defaultdoc');
    }

    /**
     * Returns all available denominations.
     *
     * @return array
     */
    public function getDenominations(): array
    {
        return (new DenominacionMoneda())->all([], ['valor' => 'ASC']);
    }

    /**
     * Returns headers and columns available by user permissions.
     *
     * @return array
     */
    public function getGridHeaders(): array
    {
        return SalesDataGrid::getDataGrid($this->user);
    }

    /**
     * Returns a random token to use as transaction id.
     *
     * @return string
     */
    public function getNewToken(): string
    {
        return $this->multiRequestProtection->newToken();
    }

    /**
     * Returns all available payment methods.
     *
     * @return FormaPago[]
     */
    public function getPaymentMethods(): array
    {
        $formasPago = [];

        $formasPagoCodeList = explode('|', $this->getSetting('formaspago'));
        foreach ($formasPagoCodeList as $value) {
            $formasPago[] = (new FormaPago())->get($value);
        }

        return $formasPago;
    }

    /**
     * Read the log.
     *
     * @return array
     */
    protected function getMessages(): array
    {
        $log = [];
        $level = ['critical', 'warning', 'notice', 'info', 'error'];

        foreach ($this->toolBox()->log()::read('master', $level) as $m) {
            if (in_array($m['level'], array('warning', 'critical', 'error'))) {
                $log[] = ['type' => 'warning', 'message' => $m['message']];
                continue;
            }

            $log[] = ['type' => $m['level'], 'message' => $m['message']];
        }

        return $log;
    }

    /**
     * Return POS setting value by given key.
     *
     * @param string $key
     * @return mixed
     */
    protected function getSetting(string $key)
    {
        return $this->toolBox()->appSettings()->get('pointofsale', $key);
    }

    /**
     * Return Current Session Storage Object
     *
     * @return OrderStorage
     */
    protected function getStorage(): OrderStorage
    {
        return $this->session->getStorage();
    }

    /**
     * @return TerminalPuntoVenta
     */
    protected function getTerminal(): TerminalPuntoVenta
    {
        return $this->session->terminal();
    }

    /**
     * Load User session if is open and set the view template.
     *
     * @return void
     */
    protected function loadTemplate(): void
    {
        if ($this->session->isOpen()) {
            $this->setTemplate('\POSpro\Layout\SalesScreen');
            return;
        }

        $this->setTemplate('\POSpro\Layout\SessionScreen');
    }

    /**
     * @param $document
     * @return void;
     */
    protected function printVoucher($document)
    {
        $message = Printer::salesTicket($document, $this->getTerminal()->anchopapel);

        $this->toolBox()->log()->info($message);
    }

    /**
     * Print closing voucher.
     *
     * @return void;
     */
    protected function printClosingVoucher()
    {
        $message = Printer::cashupTicket($this->session->getSession(), $this->empresa, $this->getTerminal()->anchopapel);

        $this->toolBox()->log()->info($message);
    }

    /**
     * @param $content
     * @param bool $encode
     */
    protected function setResponse($content, bool $encode = true): void
    {
        $response = $encode ? json_encode($content) : $content;
        $this->response->setContent($response);
    }

    protected function setNewToken(): void
    {
        $this->token = $this->getNewToken();
    }

    protected function userLogin()
    {
        $nick = $this->request->request->get('userNick');
        $password = $this->request->request->get('userPassword');
        $user = new User();

        if ($user->loadFromCode($nick) && $user->enabled) {
            if ($user->verifyPassword($password)) {
                $user->newLogkey(self::toolBox()::ipFilter()->getClientIp());
                $user->save();

                $expire = time() + FS_COOKIES_EXPIRE;
                $this->response->headers->setCookie(
                    new Cookie('fsNick', $user->nick, $expire, FS_ROUTE));
                $this->response->headers->setCookie(
                    new Cookie('fsLogkey', $user->logkey, $expire, FS_ROUTE));
                $this->response->headers->setCookie(
                    new Cookie('fsLang', $user->langcode, $expire, FS_ROUTE));
                $this->response->headers->setCookie(
                    new Cookie('fsCompany', $user->idempresa, $expire, FS_ROUTE));

                $this->session->updateUser($user);

                $message = $this->toolBox()->i18n()->trans('login-ok', ['%nick%' => $user->nick]);
                $this->toolBox()->Log()->info($message);
                return;
            }
        }

        $this->toolBox()->i18nLog()->warning('user-cant-login');
    }

    /**
     * @return bool
     */
    protected function validateRequest(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            $this->toolBox()->i18nLog()->warning('not-allowed-modify');
            $this->buildResponse();
            return false;
        }

        $token = $this->request->request->get('token');

        if (empty($token) || false === $this->multiRequestProtection->validate($token)) {
            $this->toolBox()->i18nLog()->warning('invalid-request');
            $this->buildResponse();
            return false;
        }

        if ($this->multiRequestProtection->tokenExist($token)) {
            $this->toolBox()->i18nLog()->warning('duplicated-request');
            $this->buildResponse();
            return false;
        }

        $this->setNewToken();
        return true;
    }
}
