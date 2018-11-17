<?php
namespace Strawframework\Vendors;
/*
 *  General QrCode
 *
 *  Zack Lee
 */
	
    $QR_BASEDIR = LIBRARY_PATH . DS . 'Vendos' . DS .'phpqrcode'.DS;
	
	// Required libs
	
	include $QR_BASEDIR."qrconst.php";
	include $QR_BASEDIR."qrconfig.php";
	include $QR_BASEDIR."qrtools.php";
	include $QR_BASEDIR."qrspec.php";
	include $QR_BASEDIR."qrimage.php";
	include $QR_BASEDIR."qrinput.php";
	include $QR_BASEDIR."qrbitstream.php";
	include $QR_BASEDIR."qrsplit.php";
	include $QR_BASEDIR."qrrscode.php";
	include $QR_BASEDIR."qrmask.php";
	include $QR_BASEDIR."qrencode.php";

    class GenQrcode{
        //example in strawframework
        // \vendors\GenQrcode::run('http://fruit.zlizhe.com', 'uploads/qrcode.png'); 
        /**
         *  生成二维码
         *  @param $txt 生成文本 或 链接
         *  @param $output 保存二维码为图像 png false 则直接输出
         *  @param $level 可恢复级别
         *  @param $size qrcode 大小
         */
        public static function run($txt, $output=false, $level=QR_ECLEVEL_L, $size=6, $margin = 4, $saveandprint=false){

            return \QRcode::png($txt, $output, $level, $size, $margin, $saveandprint); 
        }
    }
