import fs from 'node:fs';
const rows=[];
const add=(category,name,price,specs,stock=16,brand='Novea')=>rows.push({category,name,slug:name.toLowerCase().replace(/[^a-z0-9]+/g,'-'),sku:'BC-'+category.toUpperCase()+'-'+(rows.length+1),price,specs,stock,brand,summary:({cpu:'La puissance au cœur de votre prochain projet.',motherboard:'Une base solide, prête pour vos ambitions.',memory:'Gardez le rythme, même avec vos projets les plus exigeants.',gpu:'Donnez une nouvelle dimension à vos images.',psu:'Une alimentation stable et silencieuse.',case:'De l’espace pour créer. Un design pour durer.',storage:'Vos projets, accessibles en un instant.',cooler:'Des températures maîtrisées, en toute discrétion.'}[category]||'Un équipement pensé pour votre quotidien.')});
add('cpu','Novea Core 8',32900,{socket:'NC5',cores:8,frequency:4200,tdp:105,integratedGraphics:true});
add('cpu','Novea Core 12',44900,{socket:'NC5',cores:12,frequency:4400,tdp:125,integratedGraphics:false});
add('cpu','Altis Compute 6',19900,{socket:'AT4',cores:6,frequency:3600,tdp:65,integratedGraphics:true},8,'Altis');
add('motherboard','Novea Base B650',18900,{socket:'NC5',formFactor:'ATX',memoryType:'DDR5',memorySlots:4,maxMemory:128,storageInterfaces:['NVMe','SATA']});
add('motherboard','Altis Studio M4',12900,{socket:'AT4',formFactor:'mATX',memoryType:'DDR4',memorySlots:2,maxMemory:64,storageInterfaces:['SATA']},8,'Altis');
add('memory','Velora Flow 32 Go',10900,{memoryType:'DDR5',capacity:32,modules:2,frequency:6000},28,'Velora');
add('memory','Velora Flow 64 Go',19900,{memoryType:'DDR5',capacity:64,modules:2,frequency:6000},2,'Velora');
add('memory','Velora Classic 16 Go',4900,{memoryType:'DDR4',capacity:16,modules:2,frequency:3200},9,'Velora');
add('gpu','Altis Arc G70',54900,{length:285,slots:2,tdp:220,recommendedWatts:650},14,'Altis');
add('gpu','Altis Arc G90 Studio',89900,{length:345,slots:3,tdp:350,recommendedWatts:850},3,'Altis');
add('gpu','Altis Arc G50',29900,{length:230,slots:2,tdp:130,recommendedWatts:450},0,'Altis');
add('psu','Orven Pulse 750',11900,{watts:750,efficiency:'Gold',formFactor:'ATX',connectors:['24-pin','8-pin EPS','2x8-pin PCIe']},20,'Orven');
add('psu','Orven Pulse 450',5900,{watts:450,efficiency:'Bronze',formFactor:'ATX',connectors:['24-pin','8-pin EPS','8-pin PCIe']},12,'Orven');
add('psu','Orven Pulse 1000',18900,{watts:1000,efficiency:'Gold',formFactor:'ATX',connectors:['24-pin','8-pin EPS','3x8-pin PCIe']},7,'Orven');
add('case','Orven Frame 01',9900,{motherboardFormats:['ATX','mATX'],maxGpuLength:330,psuFormats:['ATX'],maxCoolerHeight:170},16,'Orven');
add('case','Orven Frame Mini',7900,{motherboardFormats:['mATX'],maxGpuLength:260,psuFormats:['SFX'],maxCoolerHeight:140},9,'Orven');
add('storage','Velora Sprint 1 To',8900,{interface:'NVMe',formFactor:'M.2',capacity:1000},24,'Velora');
add('storage','Velora Archive 2 To',11900,{interface:'SATA',formFactor:'2.5',capacity:2000},10,'Velora');
add('cooler','Orven Air 150',4900,{sockets:['NC5','AT4'],height:155,type:'air'},19,'Orven');
add('cooler','Orven Air Compact',3900,{sockets:['AT4'],height:125,type:'air'},6,'Orven');
add('monitor','Altis View 27',24900,{},18,'Altis');add('keyboard','Velora Type 80',8900,{},21,'Velora');add('mouse','Velora Point',4900,{},25,'Velora');add('headset','Orven Sound',7900,{},11,'Orven');
add('prebuilt','Novea Creative Station',179900,{},4);add('prebuilt','Novea Essential',79900,{},7);
fs.writeFileSync('infrastructure/catalog.json',JSON.stringify(rows,null,2));
fs.mkdirSync('apps/storefront/public/assets/components',{recursive:true});
for(const category of [...new Set(rows.map(x=>x.category))]){
const art=category==='gpu'?`<path d="M60 104h280v106H60z" fill="url(#metal)"/><path d="M46 113h15v120H46z" fill="#344044"/><path d="M102 210h186v12H102z" fill="#bea47a"/><g fill="#1a262b" stroke="#53666b" stroke-width="3"><circle cx="142" cy="156" r="40"/><circle cx="258" cy="156" r="40"/></g><g fill="#667c80">${[142,258].map(cx=>Array.from({length:9},(_,i)=>`<path d="M${cx} 156q-35-6-22-31q26-7 22 31" transform="rotate(${i*40} ${cx} 156)"/>`).join('')).join('')}</g><g fill="#a5c4c1"><circle cx="142" cy="156" r="10"/><circle cx="258" cy="156" r="10"/></g>`:
category==='case'||category==='prebuilt'?`<path d="M113 56l141-16 49 31v204l-144 18-46-32z" fill="#29383c"/><path d="M127 70l121-14v205l-121 14z" fill="#102127" stroke="#526b6d" stroke-width="2"/><path d="M258 59l33 20v185l-33 7z" fill="url(#metal)"/><g stroke="#61b6a3" stroke-width="6" fill="#1d3036"><circle cx="185" cy="118" r="30"/><circle cx="185" cy="200" r="30"/></g><path d="M267 88v160m8-157v156m8-151v147" stroke="#192b30" stroke-width="4"/>`:
category==='memory'||category==='storage'?`<g transform="rotate(-18 200 160)"><rect x="58" y="120" width="284" height="77" rx="8" fill="url(#metal)"/><path d="M74 197h252v13H74z" fill="#c6ac76"/><g fill="#182b30">${[85,143,201,259].map(x=>`<rect x="${x}" y="137" width="42" height="42" rx="3"/>`).join('')}</g><path d="M73 126h250" stroke="#7ed7bc" stroke-width="4"/></g>`:
category==='motherboard'?`<rect x="93" y="48" width="214" height="232" rx="8" fill="#2b444a"/><g stroke="#607b7d" fill="none">${[0,1,2,3,4].map(i=>`<path d="M110 ${80+i*30}h170v${120-i*12}H130"/>`).join('')}</g><rect x="139" y="88" width="88" height="88" fill="#9aacaf"/><rect x="153" y="102" width="60" height="60" fill="#273b42"/><path d="M253 72v130m17-130v130M123 221h157m-157 19h157" stroke="#192b2e" stroke-width="12"/>`:
category==='cpu'?`<g transform="rotate(-12 200 160)"><rect x="106" y="65" width="188" height="188" rx="9" fill="#243e3f"/><rect x="123" y="82" width="154" height="154" rx="9" fill="url(#silver)"/><text x="200" y="151" text-anchor="middle" font-family="Arial" font-size="18" fill="#425758">NOVEA</text><text x="200" y="178" text-anchor="middle" font-family="Arial" font-size="24" fill="#425758">CORE</text></g>`:
`<rect x="100" y="76" width="200" height="174" rx="14" fill="url(#metal)"/><circle cx="200" cy="163" r="66" fill="#182a30" stroke="#627b80" stroke-width="3"/>${Array.from({length:9},(_,i)=>`<path d="M200 163q-57-10-37-50q45-12 37 50" fill="#425d62" transform="rotate(${i*40} 200 163)"/>`).join('')}<circle cx="200" cy="163" r="17" fill="#72b7a6"/>`;
fs.writeFileSync('apps/storefront/public/assets/components/'+category+'.svg',`<svg xmlns="http://www.w3.org/2000/svg" width="400" height="320" viewBox="0 0 400 320"><defs><linearGradient id="metal" x2="1" y2="1"><stop stop-color="#718589"/><stop offset=".45" stop-color="#354a50"/><stop offset="1" stop-color="#182a31"/></linearGradient><linearGradient id="silver" x2="1" y2="1"><stop stop-color="#e4eeed"/><stop offset=".5" stop-color="#a9bdbe"/><stop offset="1" stop-color="#d3dedb"/></linearGradient><filter id="s"><feDropShadow dy="13" stdDeviation="12" flood-opacity=".18"/></filter></defs><g filter="url(#s)">${art}</g></svg>`);
}
