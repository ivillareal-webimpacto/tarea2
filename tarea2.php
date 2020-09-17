<?php
/**
 *
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2020 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/***********************************/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Tarea2 extends Module
{
    public $hooks = array();
    
    
    //////////////////////////////////////
    //construct
    /////////////////////////////////////
    public function __construct()
    {
        $this->name = 'tarea2';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Webimpacto';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Importador de productos');
        $this->description = $this->l('Módulo que importa a Prestashop todos los productos que aparecen');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }
        return true;
    }

    public function getContent()
    {
        $this->_html = '<h2>'.$this->displayName.'</h2>';
        if (Tools::getIsset('subirCSVImpApad')) {
            $target_dir = _PS_BASE_URL_.__PS_BASE_URI__."modules/tarea2/";
            $target_file = $target_dir . basename($_FILES["archivocsimp_apad"]["name"]);
            $FileType = pathinfo($target_file, PATHINFO_EXTENSION);
            if ($FileType != "csv" && $FileType != "CSV") {
                $this->_html .= $this->displayError($this->l('ERROR: No es un archivo válido .csv: ').$target_file);
            } else {
                if ($file_content = Tools::file_get_contents($_FILES["archivocsimp_apad"]["tmp_name"])) {
                    $separator = chr(10);
                    $lines = explode($separator, $file_content);
                    $fila="";
                    foreach ($lines as $key => $line) {
                        $dat = explode(";", $line);

                        if (empty($dat[0]) && !isset($lines[$key+1])) {
                            continue;
                        }
                        if ($key != 0) {
                            foreach ($dat as $key => $dato) {
                                $fila.=$dato;
                                $fila.= ' ';
                            }
                            $this->importProducts($fila);
                            $fila = "";
                        }
                    }
                } else {
                    $this->_html .= $this->displayError($this->l('ERROR: Ocurrió un error al subir el archivo.'));
                }
            }
        }
        $this->displayForm();

        return $this->_html;
    }

    private function importProducts($fila)
    {
        $campos = explode(",", $fila);
        //tabla ps_product
        //$campos[1] REFERENCE;
        //$campos[2] EAN13;
        //$campos[3] WHOLESALE_PRICE;
        //$campos[4] PRICE;
        //$campos[6] Quantity;
        //tabla ps_product_lang
        //$campos[0] NOMBRE;
        //tabla ps_manufacturer
        //$campos[8] MARCA
        //tabla ps_category
        //$campos[7] CATEGORIA

        //Consultamos la marca sino existe la añadimos
        $marca = addslashes(utf8_encode(trim($campos[8])));
        if (($row = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'manufacturer
                            WHERE name = "'.$marca.'"')) == 0) {
            //creamos la marca
            $sql = 'INSERT INTO '._DB_PREFIX_.'manufacturer (name, active) VALUES ("'.$marca.'", "1")';
            Db::getInstance()->execute($sql);
            $id_manufacturer = Db::getInstance()->Insert_ID();

            $sql = 'INSERT INTO '._DB_PREFIX_.'manufacturer_shop (id_manufacturer, id_shop)
             VALUES ("'.$id_manufacturer.'", "1")';
            Db::getInstance()->execute($sql);

            $sql = 'INSERT INTO '._DB_PREFIX_.'manufacturer_lang (id_manufacturer, id_lang)
             VALUES ("'.$id_manufacturer.'", "1")';
            Db::getInstance()->execute($sql);
        } else {
            $id_manufacturer=$row['id_manufacturer'];
        }

        //Consultamos la categoria sino existe la creamos
        $categoria = addslashes(utf8_encode(trim($campos[7])));
        if (($row = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'category_lang
                            WHERE name = "'.$categoria.'"')) == 0) {
            //creamos la categoria
            $sql = 'INSERT INTO '._DB_PREFIX_.'category (active) VALUES ("1")';
            Db::getInstance()->execute($sql);
            $id_category = Db::getInstance()->Insert_ID();

            $sql = 'INSERT INTO '._DB_PREFIX_.'category_lang (id_category,id_shop,id_lang,name) 
            VALUES ("'.$id_category.'", "1", "1","'.$categoria.'")';
            Db::getInstance()->execute($sql);

            $sql = 'INSERT INTO '._DB_PREFIX_.'category_shop (id_category,id_shop) VALUES ("'.$id_category.'", "1")';
            Db::getInstance()->execute($sql);
        } else {
            $id_category=$row['id_category'];
        }

        //sacamos el iva
        $iva = utf8_encode(trim($campos[5]));
        if (($row = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'tax t
                                    LEFT JOIN '._DB_PREFIX_.'tax_rule r on t.id_tax= r.id_tax
                            WHERE t.rate = "'.$iva.'" AND r.id_country="6"')) != 0) {
            $id_tax_rules_group=$row['id_tax_rules_group'];
        } else {
            //si no existe la regla, la creamos
            $sql = 'INSERT INTO '._DB_PREFIX_.'tax_rules_group (name,active) VALUES ("'.$iva.'","1")';
            Db::getInstance()->execute($sql);
            $id_tax_rules_group = Db::getInstance()->Insert_ID();

            //creamos la tax en ps_tax
            $sql = 'INSERT INTO '._DB_PREFIX_.'tax (rate,active) VALUES ("'.$iva.'","1")';
            Db::getInstance()->execute($sql);
            $id_tax = Db::getInstance()->Insert_ID();

            //creamos la ps_tax_rule
            $sql = 'INSERT INTO '._DB_PREFIX_.'tax_rule (id_tax_rules_group,id_tax,id_country)
             VALUES ("'.$id_tax_rules_group.'","'.$id_tax.'","6")';
            Db::getInstance()->execute($sql);
        }

        //Introducimos en ps_product
        $sql = 'INSERT INTO '._DB_PREFIX_.'product 
        (id_manufacturer,id_category_default,id_tax_rules_group,reference,ean13,wholesale_price,price,quantity)
         VALUES ("'.$id_manufacturer.'","'.$id_category.'","'.$id_tax_rules_group.'","'.$campos[1].'",
         "'.$campos[2].'","'.$campos[3].'","'.$campos[4].'","'.$campos[6].'")';
        Db::getInstance()->execute($sql);
        $idProduct= Db::getInstance()->Insert_ID();

        //Introducimos en ps_product_lang
        $sql = 'INSERT INTO '._DB_PREFIX_.'product_lang (id_product,id_shop,id_lang,name) 
        VALUES ("'.$idProduct.'","1","1","'.$campos[0].'")';
        Db::getInstance()->execute($sql);

        $sql = 'INSERT INTO '._DB_PREFIX_.'category_product (id_category,id_product)
         VALUES ("'.$id_category.'","'.$idProduct.'")';
        Db::getInstance()->execute($sql);
    }

    //////////////////////////////////////
    //Admin form
    /////////////////////////////////////
    private function displayForm()
    {
        $this->_html .='';
                       
        //IMPORTAR
        $this->_html .= '<form method="post" action="'.$_SERVER['REQUEST_URI'].'" enctype="multipart/form-data">
			<fieldset>
				<legend><img src="'.$this->_path.'logo.gif" alt="" title="" /> '.$this->l('Configuración').'</legend>
				<div class="alert alert-info" style="width: 100%;text-align:left;">
				'.$this->l('Activa/desactiva extras masivamente').'</div>
                                <br/><br>
                                ';
        $this->_html .= '
				<label>'.$this->l('Archivo .csv').'</label>
				<div class="margin-form">
					<input type="file" name="archivocsimp_apad" />
                                        <a target="_blank" style="font-size: 14px;" 
                                         href="'._PS_BASE_URL_.__PS_BASE_URI__.'modules/tarea2/template.csv">
                                         '.$this->l("Descargar plantilla de ejemplo del csv").'</a>
				</div>
				<div class="margin-form clear">
				    <input type="submit" name="subirCSVImpApad" value="'.$this->l('Cargar').'" class="button" />
				</div>
			</fieldset>
		</form>';
    }

    /**
     * Función para quitar caracteres indeseados del input.
     */
    private function limpiarInput($string)
    {
        return preg_replace("/[^a-zA-Z0-9]/", "", $string);
    }
}

function console_log($data)
{
    echo '<script>';
    echo 'console.log('. json_encode($data) .')';
    echo '</script>';
}
