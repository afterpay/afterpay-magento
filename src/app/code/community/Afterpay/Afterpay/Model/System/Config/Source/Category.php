<?php
class Afterpay_Afterpay_Model_System_Config_Source_Category
{
    public function getCategoriesTreeView()
    {
        $categoryList = array();

        $categories = Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSort('path', 'asc')
            ->addFieldToFilter('is_active', array('eq'=>'1'))
            ->load()
            ->toArray();

        foreach ($categories as $catId => $category)
        {
            if (isset($category['name'])) {
                $categoryList[] = array(
                    'label' => $category['name'],
                    'level'  =>$category['level'],
                    'value' => $catId
                );
            }
        }

        return $categoryList;
    }

    public function toOptionArray()
    {
        $options = array();

        $categoriesTreeView = $this->getCategoriesTreeView();

        foreach ($categoriesTreeView as $cat)
        {
            $hyphen = '';
            for ($i = 1; $i < $cat['level']; $i++) {
                $hyphen .= '--';
            }
            $options[] = array(
               'label' => $hyphen . $cat['label'],
               'value' => $cat['value']
            );
        }

        return $options;
    }
}
