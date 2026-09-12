function getCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
}

interface ApiError {
    error?: string;
}

/**
 * POSTs JSON to a Laravel route using the browser fetch API. Laravel's
 * default web middleware group always sets an XSRF-TOKEN cookie; sending it
 * back as the X-XSRF-TOKEN header is how Laravel verifies the request
 * without needing axios (which does this automatically) or a csrf-token
 * meta tag.
 */
export async function postJson<T>(url: string, payload: unknown): Promise<T> {
    const token = getCookie('XSRF-TOKEN');

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify(payload),
    });

    let data: (T & ApiError) | null = null;
    try {
        data = await response.json();
    } catch {
        throw new Error('Invalid response from reporting API.');
    }

    if (!response.ok) {
        throw new Error(data?.error || 'Request failed.');
    }

    return data as T;
}
