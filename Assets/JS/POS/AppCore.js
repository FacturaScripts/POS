/**
 * This file is part of POS plugin for FacturaScripts
 * Copyright (C) 2020 Juan José Prieto Dzul <juanjoseprieto88@gmail.com>
 */
const TARGET_URL = "POS";

export function deleteOrderOnHold(code, form) {
    const elements = form.elements;

    elements.action.value = 'delete-order-on-hold';
    elements.idpausada.value = code;

    form.submit();
}

export function holdOrder(lines, form) {
    if (lines.length <= 0) {
        return false;
    }

    const elements = form.elements;

    elements.action.value = 'hold-order';
    elements.lines.value = JSON.stringify(lines);
    form.submit();
}

export function resumeOrder(callback, code) {
    const data = {
        action: "resume-order",
        code: code
    };

    baseAjaxRequest(callback, data, 'Resume order fail');
}

export function recalculate(callback, lines, form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    data.action = "recalculate-order";
    data.lines = lines;

    baseAjaxRequest(callback, data, 'Recalculate order fail');
}

export function saveNewCustomer(callback, taxID, name) {
    let data = {
        taxID: taxID,
        name: name,
        action: 'save-new-customer'
    };

    baseAjaxRequest(callback, data, 'Save new customer fail');
}

export function searchBarcode(callback, query) {
    baseSearch(callback, query, 'search-barcode');
}

export function searchCustomer(callback, query) {
    baseSearch(callback, query, 'search-customer');
}

export function searchProduct(callback, query) {
    baseSearch(callback, query, 'search-product');
}

export function roundDecimals(amount, roundRate = 1000) {
    return Math.round(amount * roundRate) / roundRate;
}

export function getElement(id) {
    return document.getElementById(id);
}

export const cartTemplateSource = () => {
    return getElement('cart-template').innerHTML;
};

export const customerTemplateSource = () => {
    return getElement('customer-template').innerHTML;
};

export const productTemplateSource = () => {
    return getElement('product-template').innerHTML;
};

function baseSearch(callback, query, action) {
    let data = {
        action: action,
        query: query
    };
    $.ajax({
        type: "POST",
        url: TARGET_URL,
        dataType: "json",
        data: data,
        success: callback,
        error: function (xhr) {
            console.error('Error searching', xhr.responseText);
            return false;
        }
    });
}

function baseAjaxRequest(callback, data, eMessage) {
    $.ajax({
        type: "POST",
        url: TARGET_URL,
        dataType: "json",
        data: data,
        success: callback,
        error: function (xhr) {
            console.error(eMessage, xhr.responseText);
        }
    });
}