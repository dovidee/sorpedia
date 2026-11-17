# Constitution (prosjektprinsipper)

Alle planer og implementasjoner skal følge disse prinsippene. Ved konflikt mellom en spesifikasjon og dette dokumentet: stopp og rapporter konflikten.

1. Strukturerte data (databasen) er sannhetskilde for produkt-ID, navn, pris og lager. Språkmodellen brukes bare til dialog, tolkning og forklaring.
2. Det er ulovlig å gi villedende produktinformasjon (markedsføringsloven). Derfor har hver spesifikasjon en kilde og et nivå, og ukjent er et gyldig og nødvendig resultat.
3. To versjoner av hver produktbeskrivelse: Verifisert (Sør-Tre og ansatt-godkjente data) og Åpen (verifiserte kjøpere kan redigere, som Wikipedia). Ansatte kan flette inn i Verifisert eller avvise.
4. Vandalisme skal koste: bare kunder som har bekreftet e-post og kjøpt minst én vare kan redigere. Prisen for å poste falsk informasjon blir prisen på en vare.
5. Alle anbefalinger skal kunne forklares, med evidens (produkttekst, regel eller kjøpsdata) og nivå (dokumentert, regelutledet, antatt).
6. Maskoten viser hele resonnementet til modellen synlig for kunden (hvitboks). Det merkes at resonnementet er modellens egen tekst og kan inneholde feil.
7. Kunden skal alltid kunne gå videre til et menneske (Support) fra chatten.
8. Nye typer spesifikasjoner krever ingen databasemigrering.
9. Kritiske regler (verifisert bruker, kildenivå, at LLM ikke setter pris) har automatiske tester.
10. Personopplysninger minimeres. Demo-brukere er syntetiske.
