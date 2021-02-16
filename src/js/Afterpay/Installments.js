/**
 * Afterpay Installments JS library
 *
 * @package   Afterpay_Afterpay
 * @author    VEN Development Team <info@ven.com>
 * @copyright Copyright (c) 2015 VEN Commerce Ltd (http://www.ven.com)
 *
 * How to use:
 *
 * Define configuration (@see Afterpay_Afterpay_Block_Catalog_Installments::getJsConfig() for detail):
 * AfterpayM1.Installments.config = { ... }
 *
 * Render installments amount on page:
 * AfterpayM1.Installments.render();
 *
 * @see app/design/frontend/base/default/template/afterpay/catalog/installments.phtml
 */
;(function (Prototype, Element, Product, console) {

    // window.console fallback
    if (!console) {
        var f = function () { };
        console = {
            log: f, info: f, warn: f, debug: f, error: f
        };
    }

    var AfterpayM1 = window.AfterpayM1 = window.AfterpayM1 || {};
    AfterpayM1.Installments = AfterpayM1.Installments || {};

    /** @see Afterpay_Afterpay_Block_Catalog_Installments::getJsConfig() for details */
    AfterpayM1.Installments.config = null;

    AfterpayM1.Installments.render = function () {

        // check all pre-requisites
        if (!Prototype || !Element) {
            console.warn('Afterpay: window.Prototype or window.Element is not defined, cannot render installments amount');
            return;
        }
        if (!Product) {
            console.warn('Afterpay: window.Product is not defined, cannot render installments amount');
            return;
        }
        if (!this.config instanceof Object) {
            console.warn('Afterpay: AfterpayM1.Installments.config is not set, cannot render installments amount');
            return;
        }

        // find all price-box elements (according to configured selectors)
        this.config.selectors = this.config.selectors.filter(function(str) {
            return str.replace(/\s/g, '').length;
        });
        var priceBoxes = Prototype.Selector.select(this.config.selectors.join(','), document);

        for (var i = 0; i < priceBoxes.length; i++) {
            try {
                // if price-box is visible
                if (!priceBoxes[i].offsetWidth || !priceBoxes[i].offsetHeight) {
                    continue;
                }

                // find 'price' elements and take value from 1st not empty one if there are several of them
                // 1st priority - "special price"
                var priceElements = Prototype.Selector.select('.special-price .price', priceBoxes[i]);
                priceElements = priceElements.concat(Prototype.Selector.select('.price', priceBoxes[i]));

                var price = null;
                for (var j = 0; j < priceElements.length; j++) {
                    price = parseFloat(priceElements[j].textContent.replace(/[^\d.]/g, ''));
                    if (price != NaN) {
                        break;
                    }
                }

                // if price isn't empty and min/max order total condition is satisfied then render installments amount
                if (price
                    && (!this.config.minPriceLimit || price >= this.config.minPriceLimit)
                    && (!this.config.maxPriceLimit || price <= this.config.maxPriceLimit)
                    && (this.config.afterpayEnabled)
                ) {

                    var oldElement = priceBoxes[i].nextSibling;
                    if (oldElement && oldElement instanceof Element
                        && Element.hasClassName(oldElement, this.config.className)) {

                        oldElement.parentNode.removeChild(oldElement);
                    }

                    var individualInstalment = price / this.config.installmentsAmount;
                    individualInstalment = Math.round(individualInstalment * 100) / 100;

                    Element.insert(priceBoxes[i], {
                        after: this.config.template.replace(this.config.priceSubstitution,
                            this.config.currencySymbol + individualInstalment.toFixed(2)
                        )
                    });

                    Element.addClassName(priceBoxes[i].nextSibling, this.config.className);
                }
                else {
                    var oldElement = priceBoxes[i].nextSibling;
                    if (oldElement && oldElement instanceof Element
                        && Element.hasClassName(oldElement, this.config.className)) {

                        oldElement.parentNode.removeChild(oldElement);
                    }
                }

            } catch (e) {
                console.log('Afterpay: Error on processing price-box element: ', e);
            }
        }

    };

})(window.Prototype, window.Element, window.Product, window.console);
