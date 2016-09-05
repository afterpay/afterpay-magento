<h2> 1.1 New Afterpay Installation </h2>
<p>This section outlines the steps to install the Afterpay plugin for the first time.</p>

<p> Magento can be installed in any folder on your server however for the purposes of this document, [MAGENTO] will refer to the root folder where you have installed your version of Magento. </p>

<ol>
<li> The Afterpay Magento plugin will be provided to you as a zip file file </li>
<li> Unzip the file and follow the instructions to copy across the files Code Directory </li>
<li> Copy all files in <em>/app/code/community/Afterpay</em> folder from the unzipped plugin into [MAGENTO] <em>/app/code/community/Afterpay Design Directory</em> </li>
<li> Copy all files in <em>/design/frontend/base/default/layout</em> to [MAGENTO] <em>/design/frontend/base/default/layout</em> </li>
<li> Copy all files in <em>/design/frontend/base/default/template</em> to [MAGENTO] <em>/design/frontend/base/default/template</em> </li>
<li> Copy all files in <em>/design/adminhtml/default/default/template</em> to [MAGENTO] <em>/design/adminhtml/default/default/template</em> Directory </li>
<li> Copy all files in <em>/app/etc</em> to [MAGENTO] <em>/app/etc</em> Directory </li>
<li> Copy all files in <em>/js</em> folder into [MAGENTO] <em>/js</em> </li>
<li> Copy all files in <em>/skin/frontend/base/default</em> folder into [MAGENTO] <em>/skin/frontend/base/default</em> </li>
<li> Login to Magento admin and go to System > Cache Management </li>
<li> Flush the Magento cache by selecting “Flush Magento Cache” </li>
<li> Check that the correct Afterpay version has been installed and then complete the Configuration steps outlined in this document </li>
</ol>

<h2> 1.2	Afterpay Configuration </h2>
<p> This configuration steps for the plugin are described in the remainder of this document. </p>

<ol>
<li> Check the version of the plugin that has been installed </li>
<li> Obtain a Merchant ID and Secret Key from Afterpay </li>
<li> Configure the Afterpay payment methods Pay Over Time </li>
<li> Configure the display of the Pay Over Time installments details to be displayed on Product and Category pages </li>
<li> Place an order on your site using the test details provided </li>
</ol>

<h2> 1.3	Upgrade Of Afterpay Installation </h2>
<p> This section outlines the steps to REMOVE the existing plugin before the upgrade.
Remove the Afterpay plugin by manually deleting the following Afterpay folders and Afterpay files. </p>

<Magento Installation Folder> <br/>
| <br/>
├── app <br/>
│   ├── code <br/>
│   │   └── local <br/>
│   │       └── Afterpay (folder) <br/>
│   ├── design <br/>
│   │   └── frontend <br/>
│   │       └── base <br/>
│   │           └── default <br/>
│   │               ├── layout <br/>
│   │               │   └── afterpay.xml <br/>
│   │               └── template <br/>
│   │                   └── afterpay (folder) <br/>
│   └── etc <br/>
│       └── modules <br/>
│           └── Afterpay_Afterpay.xml <br/>
├── js <br/>
│   └── Afterpay (folder) <br/>
└── skin <br/>
    └── frontend <br/>
        └── base <br/>
            └── default <br/>
                └── afterpay (folder) <br/>

<ol>
<li> Login to Magento admin and go to System > Cache Management </li>
<li> Flush the Magento cache by selecting "Flush Magento Cache" </li> 
<li> Check that the correct Afterpay version has been installed </li>
</ol>