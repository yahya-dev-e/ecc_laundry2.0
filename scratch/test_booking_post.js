import http from 'http';

function post(path, body) {
    return new Promise((resolve, reject) => {
        const req = http.request({
            hostname: 'localhost',
            port: 3000,
            path: path,
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Content-Length': Buffer.byteLength(body)
            }
        }, res => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: data }));
        });
        req.on('error', reject);
        req.write(body);
        req.end();
    });
}

function get(path) {
    return new Promise((resolve, reject) => {
        http.get({
            hostname: 'localhost',
            port: 3000,
            path: path
        }, res => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: data }));
        }).on('error', reject);
    });
}

async function testBooking() {
    console.log('Testing booking POST /reserver...');
    
    // Booking ML3-PE on 2026-10-02 at 00:00 - 01:00
    const body = new URLSearchParams({
        machine: 'ML3-PE',
        date: '2026-10-02',
        hours: '00:00 - 01:00'
    }).toString();

    const res = await post('/reserver', body);
    console.log('POST /reserver status:', res.status);
    console.log('Redirect Location:', res.headers.location);

    if (res.headers.location && res.headers.location.includes('calendrier')) {
        console.log('SUCCESS: Reservation succeeded and redirected to calendar!');
    } else {
        console.log('FAILED: Location did not redirect to calendar:', res.headers.location);
    }
}

testBooking().catch(console.error);
