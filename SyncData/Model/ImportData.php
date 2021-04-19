<?php


namespace Modiseh\SyncData\Model;

use Magento\Catalog\Model\ProductFactory;
use Modiseh\SyncData\Model\ProductDataProvider;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as OptionFactory;
use u2flib_server\Error;

class ImportData
{
    /**
     * @var ProductFactory
     */
    protected  $productFactory;
    /**
     * @var ProductDataProvider
     */
    protected  $productDataProvider;

    /**
     * @var OptionFactory
     */
    protected $optionFactory;

    /**
     * ImportData constructor.
     * @param ProductFactory $productFactory
     * @param ProductDataProvider $productDataProvider
     * @param OptionFactory $optionFactory
     */
    public function __construct(
        ProductFactory $productFactory,
        ProductDataProvider $productDataProvider,
        OptionFactory $optionFactory
    )
    {
        $this->productFactory=$productFactory;
        $this->productDataProvider=$productDataProvider;
        $this->optionFactory=$optionFactory;
    }

    public function execute($page="0"){
        for ($i=0;$i<=1000;$i++){
            foreach ($this->productDataProvider->getProductData($page) as $product){
                if (count($product)){
                    $exAtt=$product['custom_attributes']??[];
                    $exAtt[]=[
                        'attribute_code'=>'has_cdn_img',
                        'value'=>"Yes"
                    ];
                    $stock=$product['stock_data']??[];
                    $dimisions=[];
                    $attributes = [];
                    if (isset($product['dimension_attribute'])){
                        $dimisions=$product['dimension_attribute'];
                        unset($product['dimension_attribute']);
                    }
                    unset($product['custom_attributes']);

                    if ($product['type_id']=="configurable"){
                        $product['visibility']=4;
                        $product['status']=1;
                        $product['stock_data']=[
                            "is_in_stock"=>true
                        ];
                    }

                    $newProduct=$this->productFactory->create([
                        'data'=>$product
                    ]);
                    //$newProduct->setStockData($stock);
                    foreach ($exAtt as $key=>$value){
                        if ($product['type_id']=='configurable' && in_array($key,['size','seller_name'])){
                            continue;
                        }else{
                            $newProduct->setCustomAttribute($value['attribute_code'],$value['AttributeValue']??$value['value']);
                        }
                    }
                    if ($product['type_id']=="configurable"){
                        foreach ($dimisions as $index => $attribute) {
                            $attributeValues = [];
                            foreach ($attribute as $value) {
                                $attributeValues[] = [
                                    'label' => $attribute['name'],
                                    'attribute_id' => $attribute['id'],
                                    'value_index' => $value
                                ];
                            }
                            $attributes[] = [
                                'attribute_id' => $attribute['id'],
                                'code' => $attribute['name'],
                                'label' => $attribute['name'],
                                'position' => $index,
                                'values' => $attributeValues,
                            ];
                        }
                        $configurableOptions = $this->optionFactory->create($attributes);
                        $extensionConfigurableAttributes = $newProduct->getExtensionAttributes();
                        $extensionConfigurableAttributes->setConfigurableProductOptions($configurableOptions);
                        $extensionConfigurableAttributes->setConfigurableProductLinks($this->getAssociatedProductIds($product['sku']));
                        $newProduct->setExtensionAttributes($extensionConfigurableAttributes);
//                    $newProduct->save();
                    }

                    echo "before => ".$product['sku']."\n";
                    $splite=explode("_",$product['sku']);
                    $page=$splite[0];
                    $newProduct->save();
                    echo "Product Created Successfully => ".$product['sku']."\n";
                }
            }
        }
        echo "end At:".date("H:i:s");
    }
    private function getAssociatedProductIds($parentSku){
        $query="select entity_id from catalog_product_entity
            where type_id='simple' and sku like '".$parentSku."_%'   
        ";
        return $this->productFactory->create()->getResource()
            ->getConnection()->fetchCol($query);
    }
}