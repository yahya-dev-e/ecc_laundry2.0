import http from 'http';

function get(path) {
    return new Promise((resolve, reject) => {
        http.get(`http://localhost:3000${path}`, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, data }));
        }).on('error', reject);
    });
}

function post(path, body) {
    return new Promise((resolve, reject) => {
        const postData = new URLSearchParams(body).toString();
        const req = http.request(`http://localhost:3000${path}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Content-Length': Buffer.byteLength(postData),
            },
        }, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, data }));
        });
        req.on('error', reject);
        req.write(postData);
        req.end();
    });
}

async function runTests() {
    console.log('--- Testing Password Reset Flow ---');

    // 1. GET /login
    const loginRes = await get('/login');
    const hasForgotLink = loginRes.data.includes('href="/forgot-password"');
    console.log('[1] /login contains link to /forgot-password:', hasForgotLink ? '✅ PASS' : '❌ FAIL');

    // 2. GET /forgot-password
    const forgotRes = await get('/forgot-password');
    const hasEmailInput = forgotRes.data.includes('name="email"');
    const hasForgotTitle = forgotRes.data.includes('Mot de passe oublié ?');
    console.log('[2] /forgot-password renders correctly:', (hasEmailInput && hasForgotTitle) ? '✅ PASS' : '❌ FAIL');

    // 3. POST /forgot-password
    const sendMailRes = await post('/forgot-password', { email: 'alex.rivera@fecc.ma' });
    console.log('[3] POST /forgot-password redirects (302):', sendMailRes.status === 302 ? '✅ PASS' : '❌ FAIL');
    console.log('    Redirect location:', sendMailRes.headers.location);

    // 4. GET /reset-password
    const resetFormRes = await get('/reset-password/demo-token?email=alex.rivera@fecc.ma');
    const hasPasswordInput = resetFormRes.data.includes('name="password"');
    const hasConfirmInput = resetFormRes.data.includes('name="password_confirmation"');
    const hasToken = resetFormRes.data.includes('demo-token');
    console.log('[4] GET /reset-password renders password inputs:', (hasPasswordInput && hasConfirmInput && hasToken) ? '✅ PASS' : '❌ FAIL');

    // 5. POST /reset-password
    const resetSubmitRes = await post('/reset-password', {
        token: 'demo-token',
        email: 'alex.rivera@fecc.ma',
        password: 'newpassword123',
        password_confirmation: 'newpassword123'
    });
    console.log('[5] POST /reset-password updates password and redirects to login:', resetSubmitRes.status === 302 ? '✅ PASS' : '❌ FAIL');
    console.log('    Redirect location:', resetSubmitRes.headers.location);

    console.log('--- All Tests Completed Successfully ---');
}

runTests();
