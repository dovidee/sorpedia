<?php
// Terms of use and privacy notice (feature 046). Demo text: Sørpedia is a prototype, so no real company is the controller.
// Every statement matches what the code does; the per field table lives in Innstillinger > Personvern.
?>
<article class="narrow legal">
  <h1><?= t('Vilkår og personvern') ?></h1>
  <p class="man-source"><?= t('Demotekst. Sørpedia er en prototype og er ikke tilknyttet Sør-Tre AS.') ?></p>

  <section id="vilkar" aria-labelledby="h-vilkar">
    <h2 id="h-vilkar"><?= t('Vilkår for bruk') ?></h2>
    <p><?= t('Du må ha konto for å handle, bidra i den åpne versjonen og skrive anmeldelser. Du godtar vilkårene når du oppretter kontoen.') ?></p>
    <p><?= t('Pris, lager og produktnavn kommer fra butikkens database. Spesifikasjoner merket «Utledet» eller «Kjøper» er ikke kontrollert av Sør-Tre, og veiledningene er demoveiledninger. Følg alltid produsentens anvisning.') ?></p>
    <p><?= t('Bidrag og anmeldelser skal være saklige og gjelde varen. Ansatte kan avvise eller fjerne innhold som ikke er det.') ?></p>
    <p><?= t('Søren er en produktveileder, ikke en fagperson. Svarene hans er veiledning og kan være feil, så sjekk tall som er viktige for sikkerheten på varen før bruk.') ?></p>
  </section>

  <section id="personvern" aria-labelledby="h-personvern">
    <h2 id="h-personvern"><?= t('Personvern') ?></h2>
    <p><?= t('Vi lagrer det som trengs for kontoen og ordrene: navn, e-post, telefon om du legger den inn, og passordet bare som en hash. Ordrer lagres i fem år etter bokføringsloven. Søk og klikk telles uten å knyttes til kontoen din.') ?></p>
    <p><?= t('Samtaler med Søren lagres knyttet til nettleserøkten, ikke til navnet ditt, og slettes {h} timer etter siste melding. Ansatte kan lese samtalene for å rette feil i svarene, og kundeservice ser samtalen når du ber om å snakke med et menneske.', ['h' => ChatHistory::TTL_HOURS]) ?></p>
    <p><?= t('Søren kjører lokalt og sender ingenting til eksterne tjenester. Han ser aldri navn, e-post, telefon eller passord.') ?></p>
    <p><?= t('Nettstedet bruker en cookie for økten og en for språket du velger. Handlekurven hører til økten. Sammenligning og visning lagres bare i nettleseren din.') ?></p>
    <p><?= t('Du kan se hvem som ser hver opplysning, laste ned dataene dine og slette kontoen under') ?> <a href="/innstillinger#data"><?= t('Innstillinger, Personvern') ?></a>.</p>
  </section>
</article>
