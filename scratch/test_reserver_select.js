import http from 'http';

http.get('http://localhost:3000/reserver', (res) => {
    let data = '';
    res.on('data', chunk => data += chunk);
    res.on('end', () => {
        const selectMatch = data.match(/<select[^>]*name="machine"[^>]*>([\s\S]*?)<\/select>/i);
        if (selectMatch) {
            console.log('SELECT CONTENT:');
            console.log(selectMatch[1]);
        } else {
            console.log('No select found! Snippet:');
            console.log(data.substring(data.indexOf('MACHINE'), data.indexOf('MACHINE') + 500));
        }
    });
});
