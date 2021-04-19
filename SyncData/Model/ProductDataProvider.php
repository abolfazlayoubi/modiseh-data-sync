<?php


namespace Modiseh\SyncData\Model;
class ProductDataProvider
{
    /**
     * @var DataMapping
     */
    protected  $dataMapping;

    /**
     *
     * @param DataMapping $dataMapping
     */
    public function __construct(
        DataMapping $dataMapping
    )
    {
        $this->dataMapping=$dataMapping;
    }

    public function getProductData($itemId){
        $allProduct=[];
        foreach ($this->getProductFromServer($itemId) as $product){
            if (isset($product['children'])){
                foreach ($product['children'] as $child){
                    $allProduct[]=$this->dataMapping->MapData($child,"simple",$product);
                }
                $allProduct[]=$this->dataMapping->MapData($product,"configurable",$product);
            }else{
                $allProduct[]=$this->dataMapping->MapData($product,"simple");
            }
        }
        return $allProduct;
    }

    private function getProductFromServer($itemId="0"){
        $serverName = "172.30.132.142,1433"; //serverName\instanceName
        $connectionInfo = array( "Database"=>"ECOMAX63Gate", "UID"=>"a.ayoubi", "PWD"=>"Ayoubi@66");
        $conn = sqlsrv_connect( $serverName, $connectionInfo);

        if( $conn ) {
            echo "Connection established From ".$itemId."\n";
        }else{
            echo "Connection could not be established.\n";
            die( print_r( sqlsrv_errors(), true));
        }
        $sqlMain = "select top 500 MigrateItemCatAttDim_MASTER.* from MigrateItemCatAttDim_MASTER 
           inner join (select distinct ITEMID as ITEMID from MigrateItemOnHand) as onHand on onHand.ITEMID = MigrateItemCatAttDim_MASTER.ITEMID
            where onHand.ITEMID> '".$itemId."' order by onHand.ITEMID";
        $sqlAttr = "select top 5000 MigrateItemCatAttDim_MASTER_ATT.* from MigrateItemCatAttDim_MASTER_ATT 
           inner join (select distinct ITEMID as ITEMID from MigrateItemOnHand) as onHand on onHand.ITEMID=MigrateItemCatAttDim_MASTER_ATT.ItemID
            where onHand.ITEMID > '".$itemId."' order by onHand.ITEMID";
        $sqlImg ="select top 500 MIGRATE_SANA_IMAGE_14000123.* from MIGRATE_SANA_IMAGE_14000123
            inner join (select distinct ITEMID as ITEMID from MigrateItemOnHand) as onHand on onHand.ITEMID=MIGRATE_SANA_IMAGE_14000123.ProductID
            where onHand.ITEMID > '".$itemId."' order by onHand.ITEMID";
        $sqlQty="select ITEMID,sum(dbo.MigrateItemOnHand.CurOnhand) as qty from dbo.MigrateItemOnHand group by ITEMID order by ITEMID";

        $params = array();
        $options =  array( "Scrollable" => SQLSRV_CURSOR_DYNAMIC );
        $stmtMain = sqlsrv_query( $conn, $sqlMain , $params, $options );
        $stmtAttr = sqlsrv_query( $conn, $sqlAttr , $params, $options );
        $stmtImg = sqlsrv_query( $conn, $sqlImg , $params, $options );
        $stmtQty = sqlsrv_query( $conn, $sqlQty , $params, $options );
        //echo $row_count;


        $res=[];
        $att=[];
        $img=[];
        echo "Fetch From Database Complete Do Mapping \n";
        while( $row = sqlsrv_fetch_array( $stmtMain,2) ) {
            $itemId=$row['ITEMID'];
            $_img=sqlsrv_fetch_array($stmtImg,2);
          //  $_qty=sqlsrv_fetch_array($stmtQty,2);
            $img[$_img['ProductId']]=$_img;
          //  $qty[$_qty['ITEMID']]=$_qty;
            if (isset($img[$itemId])){
                $row['base_image']=$img[$itemId]['base_image'];
                $row['additional_images']=$img[$itemId]['additional_images'];
            }
            $res[]=$row;
            $att[]=sqlsrv_fetch_array( $stmtAttr,2);

        }

        $map=[];
        foreach ($res as $product){
            if (isset($map[$product['ITEMID']])){

                $map[$product['ITEMID']]['children'][]=$this->getSimpleSchema($product);
            }else{
                $product['dimension_attribute']=[
                    'seller_name',"size"
                ];
                $product['type_id']="configurable";
                $product['visibility']=1;
                $product['status']=1;
                $map[$product['ITEMID']]=$product;
                $map[$product['ITEMID']]['children'][]=$this->getSimpleSchema($product);
                $map[$product['ITEMID']]['custom_attributes']=[];
            }
            foreach ($att as $key=>$attribute){
                if ($attribute['ItemID']==$product['ITEMID']){
                    $attribute['attribute_code']=$this->translate()[$attribute['Attribute']]??$attribute['Attribute'];
                    $map[$product['ITEMID']]['custom_attributes'][$attribute['attribute_code']]=$attribute['AttributeValue'];
                    unset($att[$key]);
                }
            }
        }
        echo "Start At :".Date("H:i:s");
        return $map;

        //        $res=json_decode(file_get_contents("/home/abolfazl/Downloads/mainProduct.json"),true);
//        $att=json_decode(file_get_contents("/home/abolfazl/Downloads/mainPrroductAttribute.json"),true);

    }

    private function getSimpleSchema($product){
        $product['type_id']="simple";
        $product['dimension_attribute']=[
            "size"=>$product['AttributeValue'],
            "seller_name"=>"Modiseh"
        ];
        $product['visibility']=4;
        $product['status']=1;
        $product['weight']=100;
        $product['price']=10000;
        $product['stock_item']=[
            "quantity"=>10,
            "is_in_stock"=>true
        ];
        return $product;
    }
    private function translate(){
        return [
            "برند"=>"brand",
            "بسته بندی"=>"packing",
            "جنس"=>"material",
            "جنسیت"=>"gender",
            "رده سنی"=>"ages",
            "رنگ"=>"color",
            "رنگ دوم"=>"the_second_color",
            "طرح و مدل"=>"design_and_model",
            "مدل"=>"model",
            "مدل دوم"=>"the_second_model",
            "ناشر"=>"publisher",
            "نوع"=>"type",
            "کشور صاحب برند"=>"brand_country",
        ];
    }
}