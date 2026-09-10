/* Screenshot prototype manifest. UI originals are never modified.
 * Rectangles use percentages of the entire image, not of the viewport.
 * A missing screen is reported; it is never replaced by another design's page.
 */
(function (root) {
  'use strict';
  const file = stamp => `UI/ChatGPT Image 10. Sept. 2026, ${stamp}.png`;
  const hot = (x, y, w, h, target, label) => ({x, y, w, h, target, label});
  const notice = (x, y, w, h, label) => ({x, y, w, h, label, message: `${label}: Nur eine Design-Vorschau; hierfür ist kein weiterer Bildschirm dieser Serie vorhanden.`});
  const row = (items, y = .6, h = 4.9) => items.map(([target, label, x, w]) => hot(x, y, w, h, target, label));
  const goldA = [ ['home','Start',21.9,2.8], ['videos','Videos',25.1,3.1], ['articles','Beiträge',29.3,3.3], ['books','Bücher',33.8,3.0], ['live','Livestreams',37.3,5.0], ['podcast','Podcast',43.5,3.4], ['community','Community',47.7,4.6], ['about','Über uns',53.4,3.5] ];
  const goldB = [ ['home','Start',22.6,2.6], ['videos','Videos',26.1,3.1], ['articles','Beiträge',30.4,3.5], ['books','Bücher',35.1,3.0], ['live','Livestreams',38.9,5.1], ['podcast','Podcast',44.7,3.5], ['community','Community',48.8,4.8], ['about','Über uns',54.6,3.8] ];
  const blue = [ ['home','Startseite',23.0,4.5], ['articles','Beiträge',28.4,4.0], ['videos','Videos',33.1,3.3], ['books','Bücher & PDF',37.3,5.8], ['live','Live',43.7,2.9], ['topics','Themen',47.1,3.9], ['about','Über uns',51.6,4.4] ];
  function header(items, y = .6, logoX = 5.4, logoW = 15.2) {
    return [hot(logoX,y,logoW,5.0,'home','Startseite / Logo'), ...row(items,y), notice(79.9,y,5.1,4.9,'Anmelden'), notice(85.3,y,9.1,4.9,'Newsletter / Mitmachen')];
  }
  const page = (id,label,stamp,hotspots = [], extra = {}) => ({id,label,file:file(stamp),hotspots,...extra});
  const publicPage = (id,label,stamp,nav,extras = [], extra = {}) => page(id,label,stamp,[...nav,...extras],extra);
  const adminRows = [['desktop','Desktop',18.4],['projects','Projekte',23.5],['calendar','Kalender',28.6],['tasks','Aufgaben',33.8],['community','Community',39.0],['analytics','Analytics',44.2],['files','Dateien',49.4],['videos','Videos',54.6],['live','Live Studio',59.8],['ai','KI-Assistent',65.0],['integrations','Integrationen',70.2],['settings','Einstellungen',75.4]];
  function mannaNav(importPage=false){
    const rows=importPage ? [...adminRows.filter(r=>!['integrations','settings'].includes(r[0])),['import','Import Center',70.2],['integrations','Integrationen',75.4],['settings','Einstellungen',80.6]] : adminRows;
    return [hot(.8,.6,13,13,'desktop','Desktop / Logo'), ...rows.map(([id,label,y])=>hot(.5,y-2.2,13.6,4.4,id,label))];
  }
  const demo = (x,y,w,h,label) => notice(x,y,w,h,label);
  const series = [
    {id:'frontend-gold-a', group:'public', title:'Gold Editorial A · 19:44', description:'8 öffentliche Seiten. Eine zusammenhängende Serie, ohne Vermischung mit anderen Entwürfen.', start:'home', pages:[
      publicPage('home','Startseite','19_44_50 (1)',header(goldA),[hot(30,6.6,39,4.2,'live','Jetzt live'),hot(16,34,14,5,'videos','Neueste Videos'),hot(32,34,9,5,'about','Mehr über uns'),...row([['videos','Videos',2.8,18],['articles','Beiträge',21.8,19],['books','Bücher',41.5,17],['podcast','Podcast',59.1,18.8],['community','Community',78.5,18.2]],42.3,11.2),hot(74.5,56,23,25,'live','Livestream öffnen')]),
      publicPage('videos','Videos','19_44_50 (2)',header(goldA),[demo(50,9,35,27,'Video abspielen'),hot(76,48,20,17,'live','Zum Livestream'),hot(2.5,38,12,5,'videos','Alle Videos')]),
      publicPage('articles','Beiträge','19_44_51 (3)',header(goldA),[hot(77,48,20,26,'books','Buch-Empfehlung'),hot(77,77,20,12,'community','Kommentare'),demo(50,9,35,27,'Beitrag lesen')]),
      publicPage('books','Bücher & PDF','19_44_51 (4)',header(goldA),[hot(30,6.5,39,4,'live','Jetzt einschalten'),hot(2.5,83,41,7,'videos','Buch trifft Video'),demo(3,53,40,27,'Buchdetails'),demo(85,63,12,5,'Weiterlesen'),demo(84,38,12,4,'Abstimmen')]),
      publicPage('live','Livestreams','19_44_52 (5)',header(goldA),[demo(77,8,19,33,'Live-Chat'),demo(40,8,34,33,'Livestream abspielen'),demo(79,72,13,5,'Abstimmen'),hot(2.5,78,50,15,'videos','Vergangene Livestreams')]),
      publicPage('podcast','Podcast','19_44_52 (6)',header(goldA),[demo(46,8,36,29,'Podcast abspielen'),hot(79,76,5,7,'videos','Videos'),demo(26,86,6,4,'Weiterhören')]),
      publicPage('community','Community','19_44_52 (7)',header(goldA),[demo(15,31,15,5,'Mitmachen'),demo(22,53,42,12,'Diskussion öffnen'),demo(62,31,11,5,'Abstimmen')]),
      publicPage('about','Über uns','19_44_52 (8)',header(goldA),[hot(16,30.7,14.5,4.7,'videos','Unsere Vision im Video'),hot(44,53,20,7,'videos','Videoprojekte'),hot(44,62,20,8,'books','Buchprojekt'),hot(44,71,20,8,'community','Manna Community')])
    ]},
    {id:'frontend-gold-b',group:'public',title:'Gold Editorial B · 19:54',description:'10 Seiten inklusive Video-, Beitrags- und Buchdetails.',start:'home',pages:[
      publicPage('home','Startseite','19_54_01 (1)',header(goldB),[hot(49,10,38,30,'video','Featured Video ansehen'),hot(12.5,30,13,5,'videos','Videos entdecken'),...row([['videos','Videos',2.5,15.6],['articles','Beiträge',19,15.5],['books','Bücher & PDF',35,15.5],['live','Livestreams',51.7,15],['podcast','Podcast',67.7,15],['community','Community',83.8,13.5]],42.5,9),hot(3.5,57,23,25,'video','Neuestes Video'),hot(28,57,21,25,'article','Neuesten Beitrag lesen'),hot(50.5,58,21,25,'book','Buch ansehen'),hot(75,57,21,25,'live','Jetzt live dabei sein')]),
      publicPage('videos','Videos','19_54_01 (2)',header(goldB),[hot(49,8,38,27,'video','Featured Video'),hot(3.5,47,51.5,28,'video','Video öffnen'),hot(77,47,19,29,'live','Livestream'),hot(3.5,83,68,7,'video','Playlist öffnen')]),
      publicPage('video','Video ansehen','19_54_02 (3)',header(goldB),[hot(8,8,3.5,2.5,'videos','Zur Videothek'),hot(12.5,8,12,2.5,'videos','Zur Serie'),demo(6,12,56,38,'Video abspielen'),hot(65,55,28,16,'live','Zum Livestream'),hot(64,74,28,8,'community','Community öffnen'),hot(27,76,8,4,'video','Kapitel'),hot(36,76,9,4,'books','Materialien'),hot(48,76,12,4,'community','Kommentare'),demo(55,51,7,4,'Video herunterladen')]),
      publicPage('articles','Beiträge','19_54_02 (4)',header(goldB),[hot(49,9,38,25,'article','Featured Beitrag lesen'),hot(3,47,56,38,'article','Beitrag lesen'),hot(62,47,16,38,'article','Beliebten Beitrag lesen'),hot(82,70,13,17,'book','Buch-Empfehlung')]),
      publicPage('article','Beitrag lesen','19_54_02 (5)',header([['home','Start',24.4,3],['videos','Videos',27.7,3.3],['articles','Beiträge',31.2,4],['books','Bücher',36,3],['live','Livestreams',39.3,4.7],['podcast','Podcast',44.5,3.5],['community','Community',48.5,4.8],['about','Über uns',54,3.6]],.5,9.6,17),[hot(13.9,8,4.2,2.5,'articles','Alle Beiträge'),demo(9.8,62.2,16.8,4.8,'PDF herunterladen'),demo(27,62.2,9.5,4.8,'Vorlesen'),hot(64,80,24,13,'video','Passendes Video'),hot(64,60,24,16,'book','Buch-Empfehlung'),hot(9.8,97,50,2.5,'community','Kommentare')]),
      publicPage('books','Bücher & PDF','19_54_03 (6)',header(goldB,4),[hot(3.5,51,46,20,'book','Buch-Empfehlungen'),hot(50,51,23,20,'book','Neue Bücher'),hot(3.5,77,70,10,'book','Beliebte Bücher'),hot(76,73,22,14,'video','Buch trifft Video'),demo(84,37,12,4,'Abstimmen'),demo(85,62,9,4,'Weiterlesen')]),
      publicPage('book','Buchdetails','19_54_03 (7)',header(goldB),[hot(14.4,8.7,4,2.6,'books','Zur Bibliothek'),demo(61,20.5,24,5.5,'Buch kaufen'),demo(61,26.8,24,4.5,'PDF kaufen'),demo(61,31.9,24,4.5,'E-Book kaufen'),demo(61,37.3,24,4.5,'Leseprobe öffnen'),hot(62,55,27,9,'video','Passendes Video'),hot(62,68,26,8,'article','Passender Beitrag'),hot(62,81,27,9,'books','Ähnliche Bücher')]),
      publicPage('live','Livestream & Chat','19_54_03 (8)',header(goldB),[demo(61.5,12,31,42,'Live-Chat'),demo(9,12,52,39,'Livestream abspielen'),demo(61.5,82,11,4,'Abstimmen'),hot(75,61,21,26,'video','Aufzeichnung ansehen'),hot(3.5,89,69,6,'community','Community')]),
      publicPage('podcast','Podcast','19_54_04 (9)',header(goldB),[demo(49,10,38,29,'Podcast abspielen'),demo(4,54,26,34,'Episode öffnen'),hot(80,78,15,6,'books','Empfohlenes Material')]),
      publicPage('community','Community','19_54_05 (10)',header(goldB),[demo(12,32,14,5,'Mitmachen'),demo(23,53,48,31,'Diskussion öffnen')])
    ]},
    {id:'frontend-blue',group:'public',title:'Blue Library · 19:16–19:22',description:'7 öffentliche Seiten: Start, Beiträge, Videos, Bibliothek und Live.',start:'home',pages:[
      publicPage('home','Startseite','19_16_27',header(blue,.6,5.8,15),[hot(6,26,11,5,'articles','Jetzt lesen'),hot(17.8,26,10.8,5,'videos','Videos ansehen'),hot(29,26,11,5,'books','PDF herunterladen'),hot(6,35,56,20,'article','Beitrag des Tages'),hot(64,36,31,19,'live','Live ansehen'),hot(43,59,23,18,'video','Aktuelle Videos'),hot(69,59,25,20,'books','Bücher & Materialien')]),
      publicPage('videos','Videos','19_22_38 (1)',header(blue,.6,5.8,15),[hot(6,44,64,22,'video','Video der Woche'),hot(6,70,64,17,'video','Video ansehen'),hot(74,33,20,30,'video','Playlist öffnen'),hot(23,33,14,4,'live','Live-Aufzeichnungen')]),
      publicPage('video','Video ansehen','19_22_39 (2)',header(blue,.6,5.8,15),[hot(10,7.5,3.5,2,'videos','Alle Videos'),hot(57,49,8.5,5,'books','PDF zum Thema'),hot(43,64,22,10,'article','Passender Beitrag'),hot(43,79,22,10,'books','Bücher & Materialien'),demo(6,10,59,36,'Video abspielen'),demo(69,65,24,23,'Kommentar schreiben')]),
      publicPage('articles','Beiträge','19_22_39 (3)',header(blue,.6,5.8,15),[hot(6,42,55,19,'article','Beitrag des Tages'),hot(6,74,55,23,'article','Beitrag lesen'),hot(66,41,28,18,'live','Live ansehen'),demo(80,34,9,4,'Abstimmen')]),
      publicPage('article','Beitrag lesen','19_22_40 (4)',header(blue,.6,5.8,15),[hot(9.5,7.5,4,2,'articles','Beiträge'),hot(68,13,22,29,'video','Passendes Video'),hot(68,49,22,16,'books','Bücher & Materialien'),demo(40,47,10.3,4,'PDF herunterladen'),demo(70,89,10,4,'Kommentar schreiben')]),
      publicPage('books','Bibliothek','19_18_16',header([['home','Startseite',26,3.7],['articles','Beiträge',30.5,3.4],['videos','Videos',34.2,2.9],['books','Bücher & PDF',38,5.2],['live','Live',43.5,2.3],['topics','Themen',46.2,3.4],['about','Über uns',50,4]],.4,11.5,14),[hot(12,30.5,61,7,'live','Live ansehen'),demo(25,64.5,25,4.5,'Lesen / PDF herunterladen'),demo(12,75,61,19,'Buchdetails'),demo(74,5.4,7,3,'Abstimmen')]),
      publicPage('live','Livestream & Chat','19_22_40 (5)',header(blue,.6,5.8,15),[demo(75,17,20,37,'Live-Chat'),demo(4,15,43,39,'Livestream abspielen'),hot(36,61,27,23,'video','Live-Aufzeichnungen'),demo(67,80,28,4,'Frage stellen')])
    ]},
    {id:'backend-manna',group:'admin',title:'Manna Workspace · 19:29 / 19:44',description:'Desktop, Projekte, Planung, Aufgaben, Import und KI. Menüpunkte bleiben in dieser Serie.',start:'desktop',pages:[
      page('desktop','Media Desktop','19_44_41 (1)',[...mannaNav(),hot(34,69,11,4.5,'ai','KI-Unterstützung'),hot(16,69,17,4.5,'projects','Weiterarbeiten'),hot(88,69,9,4,'live','Live Studio'),hot(15.2,29,12,7,'articles','Neuer Beitrag'),hot(28,29,12,7,'videos','Neues Video'),hot(41.4,29,12,7,'live','Live starten'),hot(54,29,12,7,'import','PDF importieren'),hot(68,29,11,7,'publishing','Veröffentlichen'),hot(74,76,24,22,'community','Neue Kommentare')]),
      page('projects','Projekte','19_29_45 (2)',[...mannaNav(),hot(17,92,29,6,'tasks','Aktuelle Aufgabe'),hot(47,92,27,6,'tasks','Nächster Schritt'),hot(16,21,54,7,'projects','Projekte filtern'),demo(70,20,9,5,'Neues Projekt')]),
      page('calendar','Kalender & Planung','19_29_44 (1)',[...mannaNav(),hot(79,54,19,28,'tasks','Heutige Aufgaben'),demo(16,20,16,4,'Kalenderansicht wechseln')]),
      page('tasks','Aufgaben','19_29_45 (3)',[...mannaNav(),hot(41,20,9,3.5,'projects','Projekt auswählen'),demo(66,11,11,5,'Aufgabe erstellen')]),
      page('import','Import Center','19_29_46 (4)',[...mannaNav(true),hot(79,77,19,19,'ai','Metadaten mit KI ergänzen'),demo(17,34,13,6,'YouTube importieren'),demo(17,47,13,5,'Dateien auswählen')]),
      page('ai','KI-Assistent','19_29_46 (5)',[...mannaNav(),hot(34,94,9,4,'projects','In Projekt öffnen'),demo(69,74,10,4,'Vorschlag generieren'),demo(68,94,9,4,'Vorschlag übernehmen')])
    ]},
    {id:'backend-classic',group:'admin',title:'Classic Media Desktop · 19:14',description:'Arbeitsplatz, Videoliste und Live Studio. Eigene Klickzonen pro Bildschirm.',start:'desktop',pages:[
      page('desktop','Media Desktop','19_14_14',[hot(.6,7.5,12.5,4.5,'desktop','Desktop'),hot(.6,12.5,12.5,4.2,'projects','Projekte'),hot(.6,17.5,12.5,4,'calendar','Kalender'),hot(.6,22.4,12.5,3.6,'tasks','Aufgaben'),hot(.6,27.5,12.5,4,'community','Community'),hot(29,21,12,7,'videos','Neues Video'),hot(42,21,12,7,'live','Live starten'),hot(15.5,21,12,7,'articles','Neuer Beitrag'),hot(16,69,13.3,14,'videos','Meine Videos'),hot(43,69,13.3,14,'videos','Meine Videos'),hot(15.5,55.5,17,4.5,'videos','Weiterarbeiten'),hot(75.5,47,22,10,'live','Live Studio öffnen')],{width:1536,height:1024}),
      page('videos','Videos & Playlists','19_14_21',[hot(.8,8.5,12.5,4,'desktop','Desktop'),hot(.8,42,12.5,5,'videos','Videos'),hot(.8,48.3,12.5,4,'live','Live Studio'),demo(79,9,15,5,'Video hochladen'),demo(16,45,62,6,'Video bearbeiten'),demo(81,21,16,7,'Playlist öffnen'),demo(65,31,8,3,'Playlists')]),
      page('live','Live Studio','19_14_17',[hot(.8,8.5,12.5,4,'desktop','Desktop'),hot(.8,42,12.5,5,'live','Live Studio'),demo(53,83,18,5,'Stream beenden'),demo(17,67,8,10,'Szene wechseln'),hot(16,92,26,6,'videos','Zur Videobibliothek')])
    ]},
    {id:'series-1955',group:'reference',title:'Zusätzliche Serie · 19:55',description:'9 Original-Screens. Bildfolge zum Sichten; noch keine verifizierte Menüzuordnung.',start:'screen-1',unmapped:true,pages:['19_55_30 (1)','19_55_31 (2)','19_55_31 (3)','19_55_31 (4)','19_55_32 (5)','19_55_33 (6)','19_55_34 (7)','19_55_34 (8)','19_55_35 (9)'].map((s,i)=>page(`screen-${i+1}`,`Screen ${i+1} · ${s}`,s))},
    {id:'single-designs',group:'reference',title:'Einzelentwürfe',description:'Einzelne Konzepte getrennt von den zusammenhängenden Serien.',start:'screen-1',unmapped:true,pages:[['18_57_47','Desktop · dunkel'],['18_57_56','Desktop · hell'],['19_13_38','Entwurf 19:13:38'],['19_13_45','Entwurf 19:13:45'],['19_13_48','Entwurf 19:13:48'],['19_13_52','Entwurf 19:13:52'],['19_14_11','Desktop · kompakt'],['19_16_05','Entwurf 19:16:05'],['19_17_51','Entwurf 19:17:51'],['19_44_42 (2)','Manna · blaue Startseite']].map(([s,l],i)=>page(`screen-${i+1}`,l,s))},
    {id:'ui-kits',group:'reference',title:'Master UI-Kits',description:'Design-Systeme separat vergleichen. Keine simulierte Produktnavigation.',start:'screen-1',unmapped:true,pages:['20_00_44','20_00_57','20_01_11','20_11_08','20_12_46'].map((s,i)=>page(`screen-${i+1}`,`UI-Kit · ${s.replaceAll('_',':')}`,s))}
  ];
  root.UI_PREVIEW = {version:1,series};
  if(typeof module !== 'undefined' && module.exports) module.exports = root.UI_PREVIEW;
})(typeof window !== 'undefined' ? window : globalThis);
