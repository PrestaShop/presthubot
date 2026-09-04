# Worked examples

Real issues from this repository, with the severity the maintainers
actually applied. Use them to calibrate the boundaries - especially
Critical vs Major, where the workaround question decides it.

## Critical — [BO] Can't create Order

#### Describe the bug Can't create order, Because we can't add product to cart. #### Expected behavior Creation should be fine #### Steps to Reproduce Steps to reproduce the behavior: 1. Go to orders -> new order 2. Choose customer 3. Search a product and click on add to cart 4. Product not added to cart [screenshot] PS : a jQuery exception is displayed after clicking on add to cart [screenshot] #### Additional information * PrestaShop version: develop branch * PHP version: 7.4

## Critical — Rollback to old versions is NOK with MySQL8

#### Describe the bug When we try to make a rollback from PS177 to old versions, we have errors [screenshot] We reproduced the issue me and @Robin-Fischer-PS with those versions: - Rollback from PS1775build1 tp PS1744 - Rollback from PS1755build1 to PS1765 - Rollback from PS1755build1 to PS1769 - Rollback from PS1775build1 to PS1752 - Rollback from PS1755build1 to PS16124 - Rollback from PS1774 to PS1744 - Rollback from PS1773 to PS1744 I checked Rollback from PS1769 to PS1744 => ok [screenshot] #### Steps to Reproduce Steps to reproduce the behavior: Install Old version (PS1765, 1769, 1744, 1

## Critical — [BO] Can't access view customer page

#### Describe the bug A clear and concise description of what went wrong. #### Expected behavior Explain what you expected to happen instead. #### Steps to Reproduce Steps to reproduce the behavior: 1. Go to BO customers page 2. Create new customer 3. Click on view customer 4. See error **Screenshots** [screenshot] #### Additional information * PrestaShop version: develop branch * PHP version: 7.4 (works on 7.3)

## Critical — BO - Products page - Specific prices - MySQL 8 - The Combination applied to specific price is NOK

#### Describe the bug In the BO > Products page, when we try to add a specific price to a combination => the combination selected is NOK Thanks @nesrineabdmouleh #### Steps to Reproduce Steps to reproduce the behavior: 1. Go to BO > Catalog >Products page > Edit a product with combinations 2. In the Pricing tab > Add a specific price 3. Select a combination 4. Set an Impact on price 5. Click on Apply 6. See error: the combination is not correct, it is the next one selected **Screenshots** https://drive.google.com/file/d/1k3BBsf1zrgRXhAr6QCZLPlkHj1VJEHW_/view?usp=sharing #### Additional informa

## Critical — Error on installation - ps_eventbus not found on Addons

#### Describe the bug When installing the 178x branch, I systematically encounter an error on the "Install Addons modules" step : The following error is displayed "The module ps_eventbus could not be found on Addons." It's OK on 1.7.7.x branch and on develop branch. #### Expected behavior No error and smooth upgrade :) #### Steps to Reproduce Steps to reproduce the behavior: 1. Install 1.7.8.x 2. On step "Install Addons modules", see error **Screenshots** [screenshot] #### Additional information * PrestaShop version: 1.7.8.x * PHP version: N/A

## Critical — Multishop - Employee permissions can delete product from a store that he does not have access to

### Describe the bug and add attachments An employee can remove a product from a store to which he does not have access https://github.com/user-attachments/assets/38c749cf-3c3d-4f72-829f-1e2e048bf72e ### Expected behavior He should not be able to delete or even see information from a shop without permission. ### Steps to reproduce 1. Install a second shop 2. create a logistics employee with access only to shop 2 3. log with this account 4. Go to product page 5. Chosse "All store" 6. delete product 7. See error ### PrestaShop version(s) where the bug happened 9.0.0 OS ### PHP version(s) where t

## Major — BO - Translations - An error is displayed when we try to modify the translation of a module

### Describe the bug and add screenshots In the BO > International > Translations page, when we try to translate module, an error is displayed [screenshot] ### Expected behavior _No response_ ### Steps to reproduce 1. Go to BO > International > Translations page 2. In the Modify translations section: - Type of translation = Installed modules translations - Select your module = select any module, in my example (ps_customtext, ps_cashondelivery, ps_linklist, ps_facetedsearc,..) - Select your language 3. Click on Modify 4. See error I tried with PS1786 => OK [screenshot] ### PrestaShop version(s)

## Major — [develop] BO - Carrier wizard is broken

### Describe the bug and add attachments When I try to Add/Edit a carrier, the carrier wizard is broken. <img width="445" alt="Screenshot 2023-04-21 at 14 58 43" src="https://user-images.githubusercontent.com/16019289/233642003-34764719-43db-4d87-bbc1-32dfe0202351.png"> It is a regression as it used to work before. With PHP 7.3. I just have a warning : <img width="1440" alt="Screenshot 2023-04-25 at 11 54 11" src="https://user-images.githubusercontent.com/16019289/234241757-6d86384e-e3c5-4b22-beff-1527948c681b.png"> ### Expected behavior I should be able to see the actual carrier wizard. ### S

## Major — FO - 'Total available for each user' in cart rule not considered if the customer is signed in from the checkout page

#### Describe the bug The 'Total available for each user' in cart rule not considered if the customer is signed in from the checkout page. #### Steps to Reproduce Steps to reproduce the behavior: 1. Create cart rule Check Total available for each User = 1 Set these fields: 1.1. Information: - Name - Description - **Code** 1.2. Conditions: - Total available = 2 - **Total available for each user =1** 1.3. Actions: - Discount = 1 euro and click on Save 2. Go to FO > Sign in with john doe > apply the discount > Place the order 3. If you try to apply the cart again impossible 4. Sign out 5. Add a p

## Major — BO - An exception is thrown when adding a bad query with an alias in a wrong position only with php8

### Describe the bug and add attachments An exception is thrown when adding a bad query only with php8 **PS8.1.0 with php 8.1 : NOK** :x: [screenshot] **PS8.0.1 with php 8.1 : NOK** :x: [screenshot] **PS8.1.0 with php 7.3 : OK** :heavy_check_mark: [screenshot] **PS8.0.1 with php 7.4 : OK** :heavy_check_mark: [screenshot] https://user-images.githubusercontent.com/92912932/221572216-3c520703-d01d-451d-9daa-459c8fe01bda.mp4 https://user-images.githubusercontent.com/92912932/221572665-9396f6c7-1384-443b-a31d-fb122680d182.mp4 So, **it's a regression with php8!** ### Expected behavior An alert messa

## Major — Module ps_googleanalytics Google Analytics (4.2.2) crash if products in category to display more than 20, with GA4 enabled

### Describe the bug and add attachments Problem in ps_googleanalytics\classes\Wrapper\ProductWrapper.php line 52 ` if (count($products) > 20) { $full = false; } else { $full = true; }` When we are in category products. If in category set display products more than 20. Then in code the variable $full is altered in false we get a crash. \ps_googleanalytics\classes\Hook\HookDisplayFooter.php in the line 140 call `$products = $productWrapper->wrapProductList(isset($listing['products']) ? $listing['products'] : [], [], true);` in this case, $products required FULL product wrap with all values of t

## Major — [PS 9] Cart Rule creation fails - "Field has already been rendered" error in form.html.twig

### Describe the bug and add attachments # Bug Report: Cart Rule creation fails with "Field has already been rendered" error ## Describe the bug When trying to create a new Cart Rule in PrestaShop 9 Back Office (Catalog > Discounts > Cart Rules > Add new cart rule), clicking the button triggers an HTTP 500 Internal Server Error. The error simphony message is: ``` BadMethodCallException / RuntimeError HTTP 500 Internal Server Error An exception has been thrown during the rendering of a template ("Field "cart_rule" has already been rendered, save the result of previous render call to a variable 

## Minor — Webservice error onto product add method

### Describe the bug and add screenshots A php error occur when i try to add a product with webservice. Error is: ``` Fatal error: Uncaught InvalidArgumentException: "" cannot be interpreted as a number in /var/www/prestashop/vendor/prestashop/decimal/src/Builder.php:36 Stack trace: #0 /var/www/prestashop/vendor/prestashop/decimal/src/DecimalNumber.php(73): PrestaShop\Decimal\Builder::parseNumber('') #1 /var/www/prestashop/classes/Product.php(883): PrestaShop\Decimal\DecimalNumber->__construct('') #2 /var/www/prestashop/classes/Product.php(849): ProductCore->fillUnitRatio(false) #3 /var/www/pr

## Minor — Autoupgrade doesn't detect latest version

### Describe the bug and add attachments I will upgrade Prestashop 8.0.5 to 8.1.2 But the autoupgrade module doesn't detect the latest version. Also manual upgrade have a lot of problems Autoupgrade module shows this: Congratulations, you are already using the latest version available! Your current PrestaShop version: 8.0.5 Your current PHP version: 8.0.30 Latest official version for minor channel.: 8.0 stable - (8.0.5) [screenshot] How can I fix it? ### Expected behavior Get the latest version 8.1.2 ### Steps to reproduce ? ### PrestaShop version(s) where the bug happened 8.0.5 ### PHP versio

## Minor — BO - A bad display of buttons in combinations tab with product page V2 when multistore enabled

### Describe the bug and add screenshots A bad display of buttons in combinations tab with product page V2 when multistore enabled [screenshot] [screenshot] ### Expected behavior The buttons are well displayed ### Steps to reproduce 1. Enable multistore and create a new shop 2. BO > Advanced Parameters > New & Experimental Features > Enable new product page 3. Create a product with combinations 4. Click to generate a new combinations 5. See error >> the buttons (edit, delete, radio) are bad displayed ### PrestaShop version(s) where the bug happened 8.0.x, 8.0.0 ### PHP version(s) where the bug

## Minor — BO - Display error on modal "add new specific price" in product page V2

### Describe the bug and add screenshots Two display errors on "Add new specific price" modal in BO on product page V2. - The the loader block at the beginning of "Add new specific price" modal is a bit too long on the right: [screenshot] - The succes alert when we save a specific price has no left/right margin: [screenshot] It should be like the error alert: [screenshot] ### Expected behavior - Reduce the width loader block - Add left/right margins on success alert ### Steps to reproduce - Go to BO > Advanced Parameters > New & Experimental Features - Set to "Enabled" the "New product page - 

## Minor — Support two fields with 'type' => 'categories' in HelperForm

#### Describe the bug It's impossible to have two or more fields of type "categories" in an HelperForm : the js interactivity (check all, ...) in the second one target the first one. #### Expected behavior Support two fields without bug #### Steps to Reproduce Steps to reproduce the behavior: 1. Create or complete an helperForm with 2 fields of type "categories" 2. Load the controller 3. Click on checkall on the second field 4. Try to open a folder in the second field #### Additional information * PrestaShop version: All * PHP version: N/A

## Minor — Wrong popup displayed when the custom text of feature is above 255 char

**Version** Prestashop 1.7.7.1 PHP 7.3 **Observed:** I did not really observed it. I just read the code. Now, if you want an exemple, you just have to look in the `$adminProductController->process...` procedures from line 539 of https://github.com/PrestaShop/PrestaShop/blob/0e493eaa715fc16ac8de4e924bc691cfaa78f6f6/src/PrestaShopBundle/Controller/Admin/ProductController.php#L539-L579 **_For instance, if you put a custom text in a feature above 255 char, you've got the green popup top left saying "settings changed", but in fact it's wrong._** **Expected:** If data is added to `$adminProductContr

## Trivial — FO -  Your account  - Add/Edit Addresses - unclear alert when we have invalid address

### Describe the bug and add screenshots In the FO > Your account > Add/update address => when have an address with invalid data in the `address` field => unclear error [screenshot] In the PS1778, we have [screenshot] ### Expected behavior _No response_ ### Steps to reproduce 1. Go to FO > Sign in and try to add a new address 2. In the address field, add invalid data for example `address_` 3. Click on Save 4. See error ### PrestaShop version(s) where the bug happened 1.7.8.0, 1.7.8.1, 1.7.8.2build1 ### PHP version(s) where the bug happened 7.2, 7.3 ### If your bug is related to a module, speci

## Trivial — Use Quantity of Products in Pack does not show quantity in 8.1.7

### Describe the bug and add attachments When creating a pack of products, pack stock behavior is set to "Use quantity of products in the pack". There are 2 pack items with a quantity of 300 each, so the pack should also show 300. However, it says the quantity is 0 on the product and products page. [screenshot] [screenshot] ### Expected behavior The Product and Products pages should show the quantity of the products in the pack. ### Steps to reproduce 1. Create a pack of products 2. Add 2+ products that are in stock 3. Set Pack stock behavior to "Use quantity of products in the pack" 4. Save a

## Trivial — BO - Attributes and Features - Display NOK

#### Describe the bug On different Legacy pages, the alignment of buttons, labels and fields are NOK (see screenshots below) #### Expected behavior Alignment should be OK #### Steps to Reproduce Steps to reproduce the behavior: 1. Go to BO > Catalog > Attributes and features 2. Add a new attribute / attribute value / feature / feature value : See the fields non aligned. 3. On attribute, feature list (but also on all lists in Legacy), open the "more actions" button at the right of an item in the list : The "Edit" or "Delete" buttons does not takes the full width of the popin. **Screenshots** Ad

## Trivial — Unneeded margin on Status, delivery, documents tab

#### Describe the bug On status, delivery, documents tab etc. on order page, there is a hidden card for print purposes. However, it produces unwanted margin on screen. #### Expected behavior There should be no extra space produced by `.card`. **Screenshots** [screenshot] #### Additional information * PrestaShop version: develop * PHP version: 7.2

## Trivial — BO - View an order - Search endpoint returns HTTP 500 when 

#### Describe the bug When Search endpoint `/admin-dev/index.php/sell/orders/products/search?search_phrase=&currency_id=YYY&order_id=ZZZ&_token=AAA` is hit with empty GET parameter `search_phrase` it returns an HTTP response whose status is 500, and the response contains error message "Product search phrase must be a not empty string" This is a trivial issue with no impact for merchant. It just does not look good. #### Expected behavior Search endpoint should return something different than 500, this is not a server error. Maybe a 400. #### Steps to Reproduce Steps to reproduce the behavior: 1

## Trivial — BO - Theme & logo - Button See all theme's modules redirects to All modules

### Describe the bug and add attachments When I click on See all theme's module, all the modules are displayed, not only the ones concerning the theme. https://github.com/PrestaShop/PrestaShop/assets/16019289/44473cad-952d-4ae9-b204-46d7e130284e ### Expected behavior I should be redirected to the category Theme modules ### Steps to reproduce 1. Go to BO > Design > Theme & Logo 2. Tab Pages Configuration > Scroll down > Click See all theme's modules 3. You are redirected to Module Manager page 4. The Category should be selected to Themes modules directly :x: ### PrestaShop version(s) where the 
