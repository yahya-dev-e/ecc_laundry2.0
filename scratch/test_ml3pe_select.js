import http from 'http';

http.get('http://localhost:3000/bookings/create?machine_id=5', (res) => {
    let data = '';
    res.on('data', chunk => data += chunk);
    res.on('end', () => {
        const isML3PESelected = data.includes('<option value="ML3-PE" selected>ML3-PE</option>');
        console.log('Is ML3-PE selected when machine_id=5 passed:', isML3PESelected);
        const headerCode = data.match(/id="activeBadgeHeader"[^>]*>([^<]+)<\/span>/);
        console.log('Header machine badge:', headerCode ? headerCode[1] : 'not found');
    });
});
