Instead of another boring admin page with 12 KPI cards, imagine opening Sørpedia Control Tower and seeing a miniature, animated warehouse where products physically move through the system.

Searches arrive at intake. Product matches travel down conveyor belts. Product guides enter an advice station. Questions for Søren go to a support station. Unanswered searches get diverted into a red exception lane.

And the best part: the warehouse represents actual analytics events, not fake operational activity.

That's a much more interesting way to visualize the data you already have.
1. The concept: Sør-Tre Logistics Control Tower
iF Design - Zhejiang Cainiao Supply Chain Management Co., Ltd.
 
PITCO Engineering — Industrial Automation Engineering
 
iF Design - CAINIAO RFID: Supply Chain Visibility System
 

The Dribbble reference by Agemandi is particularly interesting because it isn't just a pretty warehouse illustration. It has:

    An isometric conveyor network.

    Moving objects representing individual items.

    Sorting destinations.

    Bottleneck detection.

    A selected item's journey and timestamps.

    Throughput graphs.

    Alerts when a lane gets congested.

That's exactly the interaction language I'd borrow. The original designer describes the animation as a simulation with illustrative operational data, so we can use the same visual concept while connecting Sørpedia to real event data. 
Dribbble
What the Sørpedia warehouse would look like
SØRPEDIA / CONTROL TOWER

LOGISTICS & DIGITAL COMMERCE INTELLIGENCE
PROTOTYPE
2 849

Produktkoblinger
16

Brukere
0

Søk uten treff
3

Aktive bidrag

WAREHOUSE FLOOR

LIVE EVENT VISUALIZATION

Normal flyt

Avvik

Sorteringslinje

Illustrative interface mockup — not connected to live events.

That's the direction. I'd make the actual version more detailed, with conveyor branches, animated packages and interactive sorting stations.
2. Map your existing KPIs to warehouse stations

This is where it gets really good: each station corresponds to a real part of your prototype.
25+ Copy-Paste AI Isometric Illustration Prompt Pack for Gemini, ChatGPT & Midjourney
 

01 — INNTAK / Incoming events

Every new analytics event enters the warehouse.

    Searches

    Product views

    Guide starts

    Chat questions

    Comparisons

Each event becomes a little parcel.
BeeVision Depo Otomasyon Sistemleri, Paket ve Palet Hacim Ölçerleri, Hacim, Ağırlık ve Boyut Ölçüm Sistemi,
    Sorter Sistemleri ve AI Destekli Depo Otomasyonu Yönetim Yazılımı
 

02 — SORTERING / Product discovery

Events are sorted into different lanes.

    Search with results

    Search without results

    Completed product guides

    Product comparisons

Unsuccessful searches get diverted to an exception lane.
Виртуальный тур - Озон Фармацевтика
 

03 — VARELAGER / Product intelligence

This represents the actual product knowledge base.

    1,726 verified specifications

    1,001 derived specifications

    3 buyer-contributed specifications

    14 documented compatibility matches

    2,849 rule-derived compatibility matches

Different package colours represent verification levels.
icograms (@icograms) on X
 

04 — KUNDESERVICE / Søren station

    Questions answered by Søren

    Questions escalated to humans

    Completed product guides

    Unresolved customer needs

Escalated questions physically move to the human support bay.
[성공 사례] AI 비전 검사로 수율 극대화: 글로벌 소재 기업의 스마트 제조 혁신
 

05 — KVALITETSKONTROLL / Data integrity

    Verified vs inferred specifications

    Documented vs rule-derived compatibility

    Synthetic seed data

    Active contributions

This station is particularly important because your prototype explicitly distinguishes verified information from assumptions.
3. The really cool part: click individual parcels

Don't make this just a looping animation. Make it an actual dashboard interaction.

Imagine clicking a red parcel on the conveyor.
Enhancing Passenger Experience with Baggage Tracking - PropVR
 
iF Design - Zhejiang Cainiao Supply Chain Management Co., Ltd.
 
Top 5 Benefits of Daifuku’s Digital Twin Solution – Sym3 | Daifuku Airport Solutions
 

A side panel opens:

Parcel #SR-00291

Property
	

Value

Type
	

Search event

Search term
	

Takrenne

Result
	

No matching result

Source
	

Website

Timestamp
	

Actual event timestamp

Status
	

Unresolved

Then you can click:

    View event details

    View related searches

    Mark as reviewed

    Add product mapping

    Create a product improvement task

This turns analytics into something you can actually operate.
4. Add warehouse management controls

I'd add five modes above the warehouse:

CONTROL MODE

Event flowWatch incoming events travel through the warehouse. Filter by time period and event type.

Illustrative interaction showing how the dashboard modes could work.

I'd also add a time selector: Last hour, Today, 7 days, 30 days.

And a playback feature:

     Play event history

     Pause

     Replay a particular period

     Filter event types

Imagine replaying a busy period and watching the warehouse fill up. That's a fantastic demo.
5. Technical implementation

I'd avoid building an actual 3D game engine. You don't need one.

Technology
	

Purpose

SVG
	

Isometric warehouse geometry

CSS transforms
	

Conveyor perspective

Framer Motion / Motion
	

Moving parcels

React
	

Interactive stations

Existing analytics_events
	

Real event feed

Existing database
	

Product and order data

Recharts
	

Throughput and trend graphs

The important architecture:

    Read actual analytics events.

    Convert each event into a warehouse item.

    Map its event type to a conveyor destination.

    Animate it through the warehouse.

    Update the corresponding KPIs.

    Allow clicking it to inspect its underlying record.

Important data distinction: Your 130 cart lines and 70,008 kr turnover, invented orders/reviews, and seed data should be visually distinguished from actual user-generated activity. The warehouse should have a clear prototype/demo-data indicator. Never animate synthetic orders as if they were live customer orders.
FEATURE: SØRPEDIA WAREHOUSE CONTROL TOWER

Build an interactive isometric warehouse simulation as the centerpiece of the Sørpedia analytics dashboard.

Reference: https://dribbble.com/shots/27815427-Baggage-Control-Airport-Baggage-Handling-Dashboard

Core concept

Replace the traditional static KPI dashboard with an interactive warehouse conveyor system.

The warehouse represents the flow of actual events through Sørpedia.

Design inspiration:

Airport baggage handling systems.
Automated parcel sorting facilities.
Industrial logistics control towers.
Dark premium operations dashboards.
Visual design

Create an isometric warehouse viewed from above at an angle.

Use:

Dark pine-charcoal background.
Muted industrial green conveyor belts.
Warm birch/amber package accents.
Sør-Tre red for exceptions and warnings.
Subtle glowing active conveyor paths.
Small moving packages representing events.
Detailed sorting stations with labels.

The warehouse should feel like a sophisticated industrial control room.

Warehouse stations
INNTAK Receives analytics events.
SØK & SORTERING Separates successful searches, unsuccessful searches and product discovery.
VAREKUNNSKAP Visualizes product specifications and compatibility relationships.
SØREN / KUNDESERVICE Handles chat questions, product guides and escalations.
KVALITETSKONTROLL Shows verified specifications, derived specifications, buyer contributions and data integrity.
Event simulation

Connect the animation to actual analytics_events.

Each event becomes a visual parcel.

Map event types to their appropriate warehouse stations.

Use different package colours for different event categories.

Implement:

Smooth conveyor movement.
Package arrival animations.
Sorting and branching.
Station activity indicators.
Clickable packages.
Selected-item detail panel.
Event history.
Throughput visualization.

The warehouse must not simply loop an arbitrary animation.

Its state must reflect the underlying event data.

If there are no events, show an empty/idle warehouse rather than manufacturing activity.

Dashboard controls

Add:

Live event mode.
Historical replay mode.
Time filters.
Event type filters.
Search without results.
Product intelligence.
Support activity.
Data quality.
Existing Sørpedia KPIs

Preserve and integrate all existing metrics:

Søk.
Søk uten treff.
Fullførte produktguider.
Spørsmål til Søren.
Sendt videre til menneske.
Sammenligninger.
Søkeord uten treff.
Cart lines and turnover by source.
Specifications by source and verification status.
Compatibility relationships.
User roles and contributions.
Data integrity

CRITICAL:

Distinguish actual user activity from fictional prototype seed data.

Orders, reviews and other synthetic records must never be presented as live operational activity.

Use the existing data model and event timestamps.

Do not invent analytics events.

Technical requirements

First inspect the existing repository and database schema.

Preserve existing functionality, authentication, routes and APIs.

Use SVG and CSS transforms for the isometric warehouse rather than introducing an unnecessary 3D engine.

Use the existing framework and animation libraries where possible.

Ensure responsive behaviour, accessible controls and good performance.

Build the warehouse as reusable components.

The result should look like a professional logistics control tower, not a generic admin dashboard.

--