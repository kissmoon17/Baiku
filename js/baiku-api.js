/*
 * BAIKU - Phase 3 API examples
 *
 * IMPORTANT:
 * This file is intentionally an example/integration helper.
 * Your existing Phase 1 main.js can continue to control the UI.
 *
 * The API endpoints are:
 *
 *   api.php?action=products
 *   api.php?action=products&category=Helmets
 *   api.php?action=products&featured=1
 *   api.php?action=product&id=1
 *
 * Example:
 *
 * const response = await fetch('api.php?action=products');
 * const data = await response.json();
 * console.log(data.products);
 */

async function getBaikuProducts(category = '') {
    const url = category
        ? `api.php?action=products&category=${encodeURIComponent(category)}`
        : 'api.php?action=products';

    const response = await fetch(url);

    if (!response.ok) {
        throw new Error('Could not load products.');
    }

    const data = await response.json();

    if (!data.success) {
        throw new Error(data.message || 'Could not load products.');
    }

    return data.products;
}

async function getBaikuFeaturedProducts() {
    const response = await fetch('api.php?action=products&featured=1');

    if (!response.ok) {
        throw new Error('Could not load featured products.');
    }

    const data = await response.json();

    if (!data.success) {
        throw new Error(data.message || 'Could not load featured products.');
    }

    return data.products;
}

async function getBaikuProduct(id) {
    const response = await fetch(
        `api.php?action=product&id=${encodeURIComponent(id)}`
    );

    if (!response.ok) {
        throw new Error('Could not load product.');
    }

    const data = await response.json();

    if (!data.success) {
        throw new Error(data.message || 'Product not found.');
    }

    return data.product;
}

async function subscribeToBaikuNewsletter(email) {
    const response = await fetch('api.php?action=newsletter', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ email })
    });

    const data = await response.json();

    if (!data.success) {
        throw new Error(data.message || 'Subscription failed.');
    }

    return data;
}
