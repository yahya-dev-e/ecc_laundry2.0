import http from 'http';

http.get('http://localhost:3000/calendrier', (res) => {
    let data = '';
    res.on('data', chunk => data += chunk);
    res.on('end', () => {
        const regex = /data-code="([^"]+)"[\s\S]*?style="background-color:\s*([^;]+);"/g;
        let match;
        const results = [];
        while ((match = regex.exec(data)) !== null) {
            results.push(`${match[1]}: ${match[2]}`);
        }
        console.log('Machine button colors on /calendrier:');
        console.log(results.join('\n'));

        // Also check server time indicator
        const hasServerTime = data.includes('id="server-time-indicator"');
        console.log('Has server-time-indicator:', hasServerTime);

        // Also check system time pointer
        const hasPointer = data.includes('id="system-time-pointer"');
        console.log('Has system-time-pointer:', hasPointer);
    });
});
