import axios from 'axios';

// Laravel envía la cookie XSRF-TOKEN y axios la devuelve como cabecera X-XSRF-TOKEN.
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

window.axios = axios;
