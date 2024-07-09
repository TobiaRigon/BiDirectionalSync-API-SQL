### Obiettivo

L'obiettivo finale del codice è il seguente:
1. Ottieni i dati SQL e i Dati API
2. Confronta i Dati Sql e i Dati Api e e trova le incongruenze
3. determina se l'incongruenza è:
	a. codice mancante in SQL
	b. codice mancante in API
	c. incongruenza dati
4. sovrascrivi il vecchio log di confronto e aggiorna il nuovo
5. per ogni incongruenza dati di tipo c. confronta il nuovo log con il vecchio log e determina se:
	 d. il dato aggiornato è SQL
	 e. il dato aggiornato è API
	 f. i due fogli di log sono uguali(impossibile determinare il dato aggiornato) 
 6. Stampa un debug che mostri:
	 1. Invio dati mancanti in sQL:
	- [x]  lista dati mancanti 
	- [x]  lista query
	- [x]  risposta/esito
2. Invia il dato mancante a API:
	- [x]  lista dati mancanti
	- [x]  risposta api
3. Aggiorna il dato API:
	- [x]  lista dati da aggiornare
	- [x]  risposta api
4. Aggiorna il dato SQL:
	- [x]  lista dati da aggiornare
	- [x]  lista query
	- [x]  risposta/esito
- 
 7. Per ciascuna delle casistiche fai delle operazioni:
	- [ ]   a. invia il dato mancante a SQL
	- [x]  b. invia il daqto mancante a API
	- [ ]   d. Aggiorna il dato in API
	- [ ]   e. Aggiorna il dato in SQL
	- [ ]   f. non fare nulla

### Richiesta generale
- Tieni conto di questa versione di base, dell'obiettivo finale e segui le mie richieste per implementare nuove funzioni o sistemare BUG.
- I punti dall'1 al 6 sono stati correttamente implementati nello script
- Ora vediamo il punto 7a(a. invia il dato mancante a SQL).
- Dimmi se ti è tutto chiaro e se hai domande o dubbi. Oltre a questo non fare nulla per il momento
### Chiarimento e approccio punto 7a

#### Problematiche
Ci sono diverse problematiche per questo punto:
- differenza di struttura tra db sql e API
- i record in API contendono l'informazione tblcode, che in Sql non è una colonna ma un Alias, come puoi notare dalle query in config.( il dato aggiunto da Api a sql dev essere recuperabile da quelle query, trmite il tblcode, anche se non è passabile in una quey)
- alcune tabelle in Sql hanno molti campi obbligatori, rendendo la generazione delle query complicata e creando molti casi specifici
#### Approccio
Per questo motivo ho pensato ad un approccio che possa aggirare il problema:
1. Identifica i dati presenti in API mancanti in Sql(gia implementato)
2. Per ciascuno dei dati mancanti in Sql crea una tabella temporanea clone dell'originale che abbia gia al suo interno un record originale qualsiasi, da db originale a db temporaneo.
3. Se la tabella temporanea è già esistente nel db temporaneo , eliminala e ricreala.
4. modifica il record della tabella temporanea nel db temporaneo con le informazioni provenienti da API
5. manda il dato aggiornato, dalla tabella temporanea del db temporaneo, alla tabella originale nel db originale.
6. elimina la tabella temporanea
7. Ripeti l'operazione per ogni dato in API mancante in SQL


