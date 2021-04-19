<?php


namespace Modiseh\SyncData\Model;


use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Model\Config as eavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Store\Model\StoreManagerInterface;

class DataMapping
{

    public $getOptionMapValue;
    /**
     * @var eavConfig
     */
    protected $eavConfig;
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;
    /**
     * @var EavSetupFactory
     */
    protected $eavSetupFactory;
    /**
     * StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * DataMapping constructor.
     * @param eavConfig $eavConfig
     * @param ResourceConnection $resourceConnection
     * @param EavSetupFactory $eavSetupFactory
     * @param StoreManagerInterface $storeManagerInterface
     */
    public function __construct(
        eavConfig $eavConfig,
        ResourceConnection $resourceConnection,
        EavSetupFactory $eavSetupFactory,
        StoreManagerInterface $storeManagerInterface
    )
    {
        $this->eavConfig=$eavConfig;
        $this->resourceConnection=$resourceConnection;
        $this->eavSetupFactory=$eavSetupFactory;
        $this->storeManagerInterface=$storeManagerInterface;
        $this->getOptionMapValue=$this->getOptionMap();
    }

    public function MapData(array $data,string $type="simple",array $parent=[]){
        $maping=[];
        $mapped=[];
        $is_recall=false;
        switch ($type){
            case "simple":
                $maping=$this->getSimpleMap();
                break;
            case "configurable":
                $maping=$this->getConfigurableMap();
        }
//        if (!isset($data['sku'])){
//            return [];
//        }
//        $data=array_merge($data['extension_attributes'],$data);
        foreach ($data as $key=>$value){

            if (isset($maping['map'][$key])){
                if (!is_array($value)){
                    $Finalvalue=trim($value);
                }else{
                    $Finalvalue=$value;
                }
                if (!in_array($maping['map'][$key],$maping['useDefaultValue'])){
                    if (isset($this->getOptionMapValue[$maping['map'][$key]])){
                        if (isset($this->getOptionMapValue[$maping['map'][$key]][$value])){
                            $mapped[$maping['map'][$key]]=$this->getOptionMapValue[$maping['map'][$key]][$value];
                        }else{
                            $this->addOptionToAttribute($maping['map'][$key],$value);
                            $is_recall=true;
                            break;
//                            $mapped[$maping['map'][$key]]=$this->getOptionMapValue[$maping['map'][$key]][$value];
                            //throw new \Exception($value." is selecteble but not fount in option in attribute ".$maping['map'][$key]);
                        }
                    }
                }else{
                  switch ($maping['map'][$key]){
                      case "status":
                          $Finalvalue=Status::STATUS_ENABLED;
                          break;
                      case "visibility":
                          $Finalvalue=Visibility::VISIBILITY_NOT_VISIBLE;
                          break;
                      case "stock_data":
                          $Finalvalue=[
                              'use_config_manage_stock' => 1,
                              'qty' => $value['quantity'],
                              'is_qty_decimal' => 0,
                              'is_in_stock' => $value['is_in_stock']
                          ];
                          break;
                      case "category_ids":
                          $Finalvalue=[];
                  }
                }
                if ($maping['map'][$key]=="sku" and $type=="simple"){
                    $Finalvalue=$this->generateChildrenSku($data,$parent,$key);

                }
                if ($maping['map'][$key]=="dimension_attribute" && $type=="configurable"){
                    if (!is_array($Finalvalue)){
                        $a=1;
                    }
                    $Finalvalue=$this->mapDimentionAttribute($Finalvalue);
                }
                $mapped[$maping['map'][$key]]=$Finalvalue;
            }
        }

        $data['custom_attributes']=array_merge($data['custom_attributes']??[],$data['dimension_attribute']??[]);
        foreach ($data['custom_attributes'] as $attribute_code => $item) {
            if (!is_array($item)){
                $item=trim($item);
            }
            if (!empty($item) && $attribute_code!="children" && gettype($attribute_code)!="integer"){
                if (is_string($item)){
                    $item=trim($item);
                }else{
                    $item=$item;
                }
                if (!in_array($maping['map'][$attribute_code]??"visibility",$maping['useDefaultValue'])) {
                    if (isset($maping['map'][$attribute_code])) {
                        if (isset($this->getOptionMapValue[$maping['map'][$attribute_code]])) {
                            if (isset($this->getOptionMapValue[$maping['map'][$attribute_code]][$item])) {
                                $item= $this->getOptionMapValue[$maping['map'][$attribute_code]][$item];
                            } else {
                                $this->addOptionToAttribute($maping['map'][$attribute_code],$item);
                                $is_recall=true;
                                break;
//                                $item= $this->getOptionMapValue[$maping['map'][$attribute_code]][$item];
                                //throw new \Exception($item . " is selecteble but not fount in option in attribute " . $maping['map'][$attribute_code]);
                            }
                        }
                    }
                }
                $mapped['custom_attributes'][]=[
                    'attribute_code'=>$attribute_code,
                    'value'=>$item
                ];
            }

        }

        if ($is_recall){
          //  $this->MapData($data,$type,$parent);
        }
        if (empty($mapped["name"])){
            $mapped["name"]=$mapped["sku"];
        }
        $mapped=$this->getAttributeSetIdBy($mapped,$parent);
        $mapped["url_key"]=$mapped['sku'];
        return $mapped;
    }
    protected function addOptionToAttribute($attributeCode,$option){
       try{
           $this->_addOptionToAttribute($attributeCode,$option);
           $this->getOptionMap([$attributeCode]);
       }catch (\Exception $exception){
           throw new \Exception($exception->getMessage());
       }

    }
    private function getSimpleMap(){
        return [
            'useDefaultValue'=>[
                "type_id","visibility","status","website_ids","weight","tax_class_id",
                "stock_data","category_ids"
            ],
            'map'=>[
                'attribute_set' => "attribute_set_id",
                'type_id' => "type_id",
                'ProductName' => "name",
                'ITEMID' => "sku",
                'itemID'=>"sku",
                "base_image"=>"image",
                "created_at"=>"created_at",
                'url_key' => "url_key",
                'price' => "price",
                'ages'=>'ages',
                "brand"=>"brand",
                "packing"=>"packing",
                "gender"=>"gender",
                "material"=>"material",
                "color"=>"color",
                "the_second_color"=>"the_second_color",
                "design_and_model"=>"design_and_model",
                "model"=>"model",
                "the_second_model"=>"the_second_model",
                "publisher"=>"publisher",
                "type"=>"type",
                "brand_country"=>"brand_country",
                'visibility' => "visibility",
                'status' => "status",
                'website_ids' => "website_ids",
                'category_ids' => "category_ids",
                'weight' => "category_ids",
                'sana_product_sku'=>'sana_product_sku',
                'description' => 'description',
                'short_description' => 'short_description',
                'tax_class_id' => "category_ids", //'taxable goods',
                'stock_item' => "stock_data",
                "size"=>"size",
                "seller_name"=>"seller_name"
            ]
        ];

    }
    private function getConfigurableMap(){
        $map=[
            'meta_description' => 'meta_description',
            'meta_keyword' => 'meta_keyword',
            'meta_title' => "meta_title",
            "can_save_configurable_attributes"=>"can_save_configurable_attributes",
            "configurable_attributes_data"=>"configurable_attributes_data",
            "dimension_attribute"=>"dimension_attribute"
        ];
        $simpleMap=$this->getSimpleMap();
        $simpleMap['map']=array_merge($simpleMap['map'],$map);
        return $simpleMap;
    }
    private function getOptionMap($seletedAttribute=[]){
        $allAttributeWithOption=$this->getOptionMapValue;
        if (!count($seletedAttribute)){
            $seletedAttribute=[
                'size','seller_name',"middle_type","type","ages"
            ];
        }
        foreach ($seletedAttribute as $attributeCode){
            $allAttributeWithOption[$attributeCode]=$this->getAttributeOptions($attributeCode);
        }
        $this->getOptionMapValue=$allAttributeWithOption;
        return $allAttributeWithOption;
        return[
            'size'=>[
                'S'=>24997,
                "M"=>25001,
                "large"=>24998,
                "10 سال"=>25002,
                "8 تا 9 سال"=>25003,
                "2 تا 3 سال"=>25004,
                "4 تا 5 سال"=>25005,
                "7 تا 8 سال"=>25006,
                "5 تا 6 سال"=>25007,
                "9 تا 10 سا"=>25008,
                "3 تا 4 سال"=>25009,
                "11 تا 12 س"=>25010,
                "6 تا 7 سال"=>25011,
                "12 سال"=>25012,
                "6 تا 12 ما"=>25013,
                "12 تا 18 م"=>25014


            ],
            'seller_name'=>[
                'Modiseh'=>24999,
                "Oshanak"=>25000
            ],
            "middle_type"=>[
                'تجهیزات ورزشی'=>25015,
                'لباس'=>25016,
                'Bags'=>25017
            ],
            "type"=>[

            ],
            "ages"=>[

            ]
        ];
    }
    private function getAttributeOptions($attribute_code){
        $options=$this->eavConfig->getAttribute(4,$attribute_code)
            ->getSource()->getAllOptions();
        $allOption=[];
        foreach ($options as $option) {
            $allOption[$option['label']]=$option['value'];
        }
        return $allOption;
    }
    private function _addOptionToAttribute($attribute_code,$option_value){
        $option=[];
        $option['attribute_id']=$this->eavConfig->getAttribute(4,$attribute_code)->getAttributeId();
//        $a[strval($option_value)][0]=$option_value;
//        foreach ($this->storeManagerInterface->getStores() as $store){
//            $a[strval($option_value)][$store->getId()]=$option_value;
//        }
        $option['values']=[$option_value];
        $this->eavSetupFactory->create()
            ->addAttributeOption($option)
        ;
    }
    private  function mapDimentionAttribute(array $attributes){
        $mapAttribute=[];
        foreach ($attributes as $attribute){
            $mapAttribute[$attribute]=$this->_mapDimentionAttribute()[$attribute];
        }
        return $mapAttribute;
    }

    private function _mapDimentionAttribute(){
        return [
            'size'=>[
                "id"=>407,
                "name"=>"size",
                "values"=>array_values($this->getOptionMapValue['size'])
            ],
            'seller_name'=>[
                "id"=>408,
                "name"=>"seller_name",
                "values"=>array_values($this->getOptionMapValue['seller_name'])
            ]
        ];
    }
    private function generateChildrenSku($product,$parent,$keyMap="sku"){
        $sku=$parent[$keyMap];
        foreach ($parent['dimension_attribute'] as $attribute){
            $sku.="_".$product['dimension_attribute'][$attribute];
        }
        return $sku;
    }
    private function getAttributeSetIdBy($mapped,$parent){
        $at=explode("/",$parent['attribute_set']);
        $mapped['attribute_set_id']=$this->getAttributeSetIdByName($at[0]);
        if (!isset($this->getOptionMapValue['middle_type'][$at[1]])){
            $this->addOptionToAttribute("middle_type",$at[1]);
        }
        if ($mapped['type_id']!="simple"){
            $mapped['custom_attributes'][]=[
                'attribute_code'=>'middle_type',
                'value'=>$this->getOptionMapValue['middle_type'][trim($at[1])]
            ];
        }


        return $mapped;
    }
    private function getAttributeSetIdByName($name){
        $data=[
            'ورزش و سرگرمی'=>314,
            'پوشاک'=>313,
            'لوازم خانه'=>315,
            'بازی و سرگرمی'=>314,
            'سوپر مارکت'=>316,
            'سوپرمارکت'=>316,
            'کالای دیجیتال'=>317,
            'کالاهای دیجیتال'=>317,
            'کالاهای ديجيتال'=>317,
            'فرهنگ و هنر'=>318,
            'آرایشی و بهداشتی'=>319,
            'کودک و نوزاد'=>320,
            'Pet Shop'=>321
        ];
        return $data[trim($name)];
    }

}