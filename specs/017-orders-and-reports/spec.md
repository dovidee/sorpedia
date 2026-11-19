# 017 Own orders and error reports

## Problem

After reporting an error in the open version, a logged in customer got no receipt and could not see or take back the report. Orders had only a thank you page right after checkout, without date, store, right of withdrawal or a way back to it later. The mascot could not help with either.

## Acceptance criteria

1. A logged in customer who reports an error gets a confirmation in the demo inbox. The inbox icon shows the number of unread messages, and a short notice appears in the bottom left corner with a link to the inbox. Opening the inbox marks the messages as read.
2. Innstillinger has the sections Ordrer and Feilmeldinger. Each shows at most three rows. With more, a button opens the full list on its own page that keeps the settings navigation, with a way back.
3. A report staff haven't handled can be taken back from the list. A handled report shows as handled and can't be taken back.
4. The order page shows order number, date, payment method, store, status, lines with the price paid, total with VAT, the last day of the 14 day right of withdrawal (angrerettloven) and the complaint period (forbrukerkjøpsloven). The demo counts the withdrawal period from the order date because it has no delivery.
5. On the order page and in Innstillinger the mascot gets the customer's own orders and reports from the database, looked up with the session user id. Never name, email, phone, address or profile picture. Payment method is included. Amounts paid are not, because prices in the prompt are references to today's price.
6. The mascot can explain how to report errors and where orders and reports are found. It can't withdraw, refund or change an order itself and sends the customer to customer service.
7. The empty merge queue and the empty customer service list show the mascot with a plank.
