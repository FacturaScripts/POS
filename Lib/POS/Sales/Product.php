<?php


namespace FacturaScripts\Plugins\POS\Lib\POS\Sales;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\CodeModel;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\POS\Model\Join\ProductoVariante;

class Product
{
    /**
     * @var ProductoVariante
     */
    protected static $product = null;

    /**
     * @var Variante
     */
    protected static $variante = null;

    /**
     * @return ProductoVariante
     */
    public static function getProductoVariante(): ProductoVariante
    {
        if (self::$product === null) {
            self::$product = new ProductoVariante();
        }

        return self::$product;
    }

    /**
     * @param string $code
     * @return float|int
     */
    public static function getStock(string $code)
    {
        $producto = self::getVariante();
        $producto->loadFromCode('', [new DataBaseWhere('referencia', $code)]);

        return $producto->stockfis ?? 0;
    }

    /**
     * @return Variante
     */
    public static function getVariante(): Variante
    {
        if (self::$variante === null) {
            self::$variante = new Variante();
        }

        return self::$variante;
    }

    /**
     * @param string $text
     * @param array $tags
     * @param string $wharehouse
     * @return array
     */
    public static function search(string $text, array $tags = [], string $wharehouse = ''): array
    {
        ///$text2 = str_replace(" ", "%", $text);

        $where = [
            new DataBaseWhere('V.referencia', $text, 'LIKE'),
            new DataBaseWhere('P.descripcion', $text, 'XLIKE', 'OR')
        ];

        if ($wharehouse && '' !== $wharehouse) {
            $where[] = new DataBaseWhere('S.codalmacen', $wharehouse);
            $where[] = new DataBaseWhere('S.codalmacen', NULL, 'IS', 'OR');
        }

        foreach ($tags as $tag) {
            $where[] = new DataBaseWhere('codfamilia', $tag, '=', 'AND');
        }

        return self::getProductoVariante()->all($where, [], 0, FS_ITEM_LIMIT);
    }

    /**
     * @param string $text
     * @return false|CodeModel
     */
    public static function searchBarcode(string $text)
    {
        $result = self::getVariante()->codeModelSearch($text, 'referencia');

        return empty($result) ? false : $result[0];
    }
}
