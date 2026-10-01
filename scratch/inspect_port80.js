import http from 'http';

http.get('http://localhost:80/', (res) => {
    let body = '';
    res.on('data', chunk => body += chunk);
    res.on('end', () => {
        console.log('Port 80 response length:', body.length);
        console.log('Title:', (body.match(/<title>[\s\S]*?<\/title>/i) || [])[0]);
        console.log('Server header:', res.headers['server']);
        console.log('X-Powered-By:', res.headers['x-powered-by']);
        console.log('First 500 chars:\n', body.slice(0, 500));
    });
});
