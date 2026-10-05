const fs = require('fs');
const path = require('path');

const filePath = path.join(__dirname, '../resources/views/calendar.blade.php');
let content = fs.readFileSync(filePath, 'utf8');

// Normalize line breaks
content = content.replace(/\r\n/g, '\n');

const searchSnippet = `                                 time: '{{ $block['timeFormatted'] }}',
                                 duration: '{{ $block['durationFormatted']                               title="{{ $mName }} • {{ $block['timeFormatted'] }} - Cliquer pour voir les détails">
                            
                            <div class="h-full p-2 flex flex-col justify-center">
                                <div class="flex items-center justify-between text-xs leading-tight">
                                    <div class="flex items-center space-x-1.5 truncate">
                                        <span class="font-mono font-bold text-[11px] bg-black/25 px-1.5 py-0.5 rounded">{{ $block['timeFormatted'] }}</span>
                                        <span class="opacity-60">•</span>
                                        <span class="font-bold text-[12px] truncate">{{ $block['machine']->name }}</span>
                                        @if($isMultiHour)
                                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-black/25 uppercase tracking-wider">{{ $block['durationFormatted'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>     </div>
                        </div>`;

const replacementSnippet = `                                 time: '{{ $block['timeFormatted'] }}',
                                 duration: '{{ $block['durationFormatted'] }}'
                             })"
                             title="{{ $mName }} • {{ $block['timeFormatted'] }} - Cliquer pour voir les détails">
                            
                            <div class="h-full p-2 flex flex-col justify-center">
                                <div class="flex items-center justify-between text-xs leading-tight">
                                    <div class="flex items-center space-x-1.5 truncate">
                                        <span class="font-mono font-bold text-[11px] bg-black/25 px-1.5 py-0.5 rounded">{{ $block['timeFormatted'] }}</span>
                                        <span class="opacity-60">•</span>
                                        <span class="font-bold text-[12px] truncate">{{ $block['machine']->name }}</span>
                                        @if($isMultiHour)
                                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-black/25 uppercase tracking-wider">{{ $block['durationFormatted'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>`;

if (!content.includes(searchSnippet)) {
    console.error('Search snippet not found!');
    process.exit(1);
}

content = content.replace(searchSnippet, replacementSnippet);
fs.writeFileSync(filePath, content, 'utf8');
console.log('Fixed calendar.blade.php cleanly!');
