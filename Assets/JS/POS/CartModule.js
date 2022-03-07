/**
 * This file is part of POS plugin for FacturaScripts
 * Copyright (C) 2018-2021 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
/*global Eta*/
import { getElement, settings } from "./Core.js";
import { recalculateRequest as recalculate } from "./Order.js";
import CartClass from "./CartClass.js";

const productEditView = getElement("productEditView");
const productEditForm = getElement("productEditForm");
const productListView = getElement('productListView');
const productListContainer = getElement("cartContainer");
const productEditTemplate = Eta.compile(getElement('product-edit-template').innerHTML);
const productListTemplate = Eta.compile(getElement('cart-template').innerHTML);

const defaultCartData = {
    'doc': {
        'codcliente': settings.customer,
        'idpausada': 'false',
        'tipo-documento': settings.document,
        'token': settings.token
    }
};

export const Cart = {
    constructor() {
        this.doc = {};
        this.lines = [];
    },

    addLine(code, description) {
        if (this.lines.some(element => {return element.referencia === code ? element.cantidad++ : false;})) {
            return;
        }
        this.lines.unshift({ referencia: code, descripcion: description });
    }
};

export function updateCart() {
    return recalculate(Cart).then(response => {
        Cart.update(response);
    });
}

function updateCartView(data) {
    productListContainer.innerHTML = productListTemplate(data.detail, Eta.config);
}

function updateEditView(index) {
    let data = Cart.getProduct(index);
    productEditForm.innerHTML = productEditTemplate(data, Eta.config);
}

function deleteProductHandler(element) {
    Cart.deleteProduct(element.dataset.index);
}

function editProductHandler(element) {
    updateEditView(element.dataset.index);

    if (true === productEditView.classList.contains('hidden')) {
        productEditView.classList.toggle('hidden');
        productListView.classList.toggle('hidden');
    }
}

function editProductFieldHandler(element) {
    const index = element.dataset.index;
    Cart.editProduct(element.dataset.index, element.dataset.field, element.value);

    updateCart().then(() => {
        updateEditView(index);
    });
}

function eventHandler(event) {
    const element = event.target;

    switch (true) {
        case element.matches('.delete-product-btn'):
            deleteProductHandler(element);
            break;
        case element.matches('.edit-product-btn'):
            editProductHandler(element);
            break;
        case element.matches('.edit-product-field'):
            editProductFieldHandler(element);
            break;
        default:
            break;
    }
}

document.addEventListener('updateCartEvent', updateCart);
document.addEventListener('updateCartViewEvent', updateCartView);
productListContainer.addEventListener('click', eventHandler);
productEditView.addEventListener('focusout', eventHandler);