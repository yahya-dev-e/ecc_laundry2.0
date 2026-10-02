import http from 'http';

function get(path, cookie = '') {
    return new Promise((resolve, reject) => {
        http.get({
            hostname: 'localhost',
            port: 3000,
            path: path,
            headers: cookie ? { 'Cookie': cookie } : {}
        }, res => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: data }));
        }).on('error', reject);
    });
}

async function run() {
    console.log('Testing User Requirements...');

    // 1. Calendrier Page - Check removal of "Créneau continu"
    const calRes = await get('/calendrier');
    console.log('GET /calendrier status:', calRes.status);
    const hasCreneauContinu = /créneau continu/i.test(calRes.body);
    console.log('Contains "créneau continu" on /calendrier?', hasCreneauContinu ? 'FAIL (still found)' : 'PASS (successfully removed)');

    // 2. Dashboard Page - Admin (default)
    const dashAdmin = await get('/dashboard');
    console.log('GET /dashboard (Admin) status:', dashAdmin.status);
    const hasTotalReservationsCard = /<span[^>]*>Vos Réservations<\/span>/i.test(dashAdmin.body) || /créneaux enregistrés/i.test(dashAdmin.body);
    console.log('Contains total reservations card on /dashboard?', hasTotalReservationsCard ? 'FAIL (still found)' : 'PASS (successfully removed)');

    const hasMachineCountCard = /13 machines/i.test(dashAdmin.body) || /7 lave-linge/i.test(dashAdmin.body);
    console.log('Contains machine count / names card on /dashboard?', hasMachineCountCard ? 'FAIL (still found)' : 'PASS (successfully removed)');

    const hasAdmin100Credits = /100 crédits/i.test(dashAdmin.body);
    console.log('Shows 100 credits for Admin on /dashboard?', hasAdmin100Credits ? 'PASS' : 'FAIL');

    // Check table machine column: should NOT have duplicated machine name or type
    const hasDuplicatedMachine = /Machine à laver 1 Omar/i.test(dashAdmin.body);
    console.log('Contains verbose machine name in history table?', hasDuplicatedMachine ? 'FAIL' : 'PASS (clean badge)');

    // 3. Toggle role to student
    await get('/toggle-role');
    const dashStudent = await get('/dashboard');
    console.log('GET /dashboard (Student) status:', dashStudent.status);
    const hasStudent8Credits = /8 crédits/i.test(dashStudent.body);
    console.log('Shows 8 credits for Student on /dashboard?', hasStudent8Credits ? 'PASS' : 'FAIL');

    // Toggle back to Admin
    await get('/toggle-role');

    // 4. Check Reservation page
    const reserverPage = await get('/reserver');
    console.log('GET /reserver status:', reserverPage.status);

    console.log('All checks complete!');
}

run().catch(console.error);
