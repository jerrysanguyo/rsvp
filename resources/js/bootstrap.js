import axios from 'axios';

const httpClient = axios.create({
    timeout: 15000,
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrfToken) {
    httpClient.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

window.axios = httpClient;

export default httpClient;
