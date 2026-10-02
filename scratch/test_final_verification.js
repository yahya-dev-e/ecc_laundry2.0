import http from 'http';

function get(path) {
    return new Promise((resolve, reject) => {
        http.get(`http://localhost:3000${path}`, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: data }));
        }).on('error', reject);
    });
}

async function run() {
    console.log('--- Testing /dashboard ---');
    const dash = await get('/dashboard');
    console.log('Status:', dash.status);
    console.log('Has Bonjour:', dash.body.includes('Bonjour,'));
    console.log('Has Heures réservables:', dash.body.includes('Heures réservables'));
    console.log('Has Historique de vos réservations:', dash.body.includes('Historique de vos réservations'));
    console.log('Has Machines badge in table:', dash.body.includes('Lave-linge') || dash.body.includes('Sèche-linge'));

    console.log('\n--- Testing Navigation in Sidebar ---');
    console.log('Has Tableau de bord link:', dash.body.includes('href="/dashboard"'));
    console.log('Has Calendrier link:', dash.body.includes('href="/calendrier"'));
    console.log('Has Reserver link:', dash.body.includes('href="/reserver"'));
    console.log('Has removed Réservations link:', dash.body.includes('href="/reservations"'));
    console.log('Has removed Machines link:', dash.body.includes('href="/machines"'));
    console.log('Has removed Réclamations link:', dash.body.includes('href="/reclamations"'));
    console.log('Has removed Paramètres link:', dash.body.includes('href="/parametres"'));

    console.log('\n--- Testing /calendrier?date=2026-10-01 ---');
    const cal = await get('/calendrier?date=2026-10-01');
    console.log('Status:', cal.status);
    // Marker position: top calculation: (94 - 60) / 60 * 52 = 29.466px
    const markerMatch = cal.body.match(/top:\s*([0-9.]+)px;.*?01:34/s);
    console.log('Marker match found:', !!markerMatch, markerMatch ? markerMatch[1] : 'none');

    console.log('\n--- Testing /reserver ---');
    const resv = await get('/reserver?machine=ML1-OM');
    console.log('Status:', resv.status);
    console.log('Has multi-slot checkboxes:', resv.body.includes('type="checkbox" name="hours"'));
    console.log('Has 8h Quota Surveillance:', resv.body.includes('Surveillance du Quota Hebdomadaire'));

    console.log('\n--- Testing Redirect of removed routes ---');
    const r1 = await get('/reservations');
    console.log('/reservations status (redirect):', r1.status, 'Location:', r1.headers.location);
    const r2 = await get('/machines');
    console.log('/machines status (redirect):', r2.status, 'Location:', r2.headers.location);
}

run().catch(console.error);
