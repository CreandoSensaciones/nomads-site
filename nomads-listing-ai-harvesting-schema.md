# Nomads Listing AI Harvesting Schema

Purpose: define the human-readable data an AI scraper should extract from partner websites so Nomads listings can later be reviewed and created consistently.

This schema is intentionally editorial and extraction-oriented. It ignores Drupal workflow, domain, path, display, moderation, revision, and other technical implementation details.

For every extracted value, the scraper should also keep:

| Tracking value | Requirement | Notes |
|---|---:|---|
| Source URL | Recommended | Page where the value was found. |
| Source text excerpt | Recommended | Short quote or nearby text that supports the value. |
| Confidence | Recommended | High, medium, or low. Use low when inferred. |

## Priority Levels

| Priority | Meaning |
|---|---|
| Required | Needed for a usable listing or strongly expected by the listing model. |
| Recommended | Important for quality, matching, search, or presentation. |
| Optional | Useful if available, but do not invent it. |

## 1. Listing Identity

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Listing name | String | Required | `Sunseed Desert Technology` | Extract the partner/project/place name. Prefer the official title used on the website. |
| Subtitle | String | Recommended | `Off-grid community and learning centre in Andalusia` | Short descriptive tagline. Summarize only when no explicit subtitle exists. |
| General description | Text | Required | `A community project focused on sustainability, learning, and low-impact living.` | Extract the broad overview of the initiative, not just a marketing slogan. |
| Project type | Classification | Recommended | `ecovillage`, `coliving`, `retreat center`, `farm project` | Choose the closest meaningful category from page language. |
| Categories | Classification list | Recommended | `community`, `nature`, `work exchange`, `retreat` | Extract search/filter categories that describe the experience. |
| Tags | Keyword list | Optional | `permaculture`, `yoga`, `remote work`, `families` | Use visible recurring concepts; avoid generating SEO-style tags not present in the source. |
| Notes | Text list | Optional | `Closed during August.` | Capture practical notes that do not fit elsewhere. |

## 2. Source And External Links

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Website | URL | Required | `https://example.org` | Extract the canonical project or partner website. |
| Links | URL list | Recommended | `Instagram`, `booking page`, `volunteer application` | Include meaningful external pages only. Avoid generic share links. |
| Email | Email | Recommended | `hello@example.org` | Extract public contact email. |
| Telephone or WhatsApp | String | Optional | `+34 600 000 000` | Preserve country code if visible. |

## 3. Location

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Address | Structured address | Recommended | `Sorbas, Almería, Spain` | Extract the most precise public address. If exact address is hidden, use locality/region/country. |
| Location coordinates | Geo point | Optional | `37.099, -2.123` | Use only if coordinates or a reliable map marker are available. |
| Surroundings | Classification list | Recommended | `rural`, `mountains`, `near beach`, `village` | Describe the physical environment around the site. |
| Settlement type | Classification list | Recommended | `village`, `farm`, `intentional community`, `retreat venue` | Extract how the place describes its settlement or site type. |

## 4. Hosting And Lodging

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Hosting offered | Boolean / relevance score | Required | `true` | Determine whether guests, nomads, volunteers, residents, or participants can stay on site. |
| Hosting description | Text | Required if hosting offered | `Shared rooms, camping spots, and private cabins are available.` | Summarize what staying there is like. |
| Lodging options | Classification list | Recommended | `shared room`, `private room`, `camping`, `van space` | Extract concrete accommodation types. |
| Capacity | Integer | Recommended | `18` | Total guest/participant beds or places if available. |
| Living standard | Classification list | Recommended | `basic`, `comfortable`, `rustic`, `off-grid` | Extract comfort level and constraints. |
| Amenities | Classification list | Recommended | `hot showers`, `laundry`, `kitchen`, `workspace` | Capture facilities relevant to a stay. |
| High season | String/list | Optional | `June to September` | Extract seasonal availability or busy periods. |
| Pets | Classification/list | Optional | `dogs allowed on request` | Capture pet policy if stated. |
| Children | Number/range | Optional | `0-5 visiting children` | Extract whether children commonly visit or are accepted. |
| Hosting images | Image URL list | Recommended | `https://example.org/room.jpg` | Extract photos of rooms, beds, bathrooms, kitchens, and guest areas. |

## 5. Pricing And Discounts

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Minimum price | Money | Recommended | `EUR 20/night` | Extract the lowest visible stay price. Include currency and period. |
| Maximum price | Money | Recommended | `EUR 650/month` | Extract upper range if shown. |
| Discount | Percentage/range | Optional | `20% for monthly stays` | Capture explicit discounts only. |
| Price notes | Text | Recommended | `Food is included Monday-Friday.` | Include what is included/excluded when visible. |

### Repeatable Price Offer Group

Use this group when the website lists multiple stay prices, packages, memberships, coupons, or accommodation options.

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Concept | Classification/string | Required | `private room`, `camping`, `volunteer stay` | Name the priced offer. |
| Price | Money/number | Required | `EUR 35/night` | Extract amount, currency, and billing period when available. |
| Period | Date range or duration | Recommended | `per week`, `May-September` | Capture validity period, season, or billing unit. |
| Relation | Classification | Optional | `per person`, `per room`, `per group` | Clarify what the price applies to. |
| Condition | Classification/text | Optional | `minimum 2 weeks`, `members only` | Extract eligibility or limitations. |
| Basic discount | String/percentage | Optional | `10% after 14 days` | Regular discount. |
| Flash discount | String/percentage | Optional | `last-minute 25%` | Temporary or coupon-style discount. |
| Flash limit | Integer | Optional | `5 coupons per week` | Extract limit if explicitly stated. |

## 6. Community And Inhabitants

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Permanent inhabitants present | Boolean / relevance score | Required | `true` | Determine whether people live on site permanently. |
| Inhabitants description | Text | Recommended | `A core group of six residents lives there year-round.` | Extract who lives there and how the community is organized. |
| Permanent inhabitants | Integer | Recommended | `6` | Extract number if stated. |
| Permanent children | Integer | Optional | `2` | Extract resident children count if stated. |
| Lifestyle | Classification list | Recommended | `communal`, `low-impact`, `spiritual`, `family-oriented` | Capture how permanent inhabitants describe their way of life. |
| Age range | Range | Optional | `25-65` | Extract resident age range if visible. |
| Gender balance | Classification/string | Optional | `mixed gender community` | Only extract if explicitly mentioned. |
| Queer people | Classification/string | Optional | `LGBTQ+ friendly` | Extract inclusivity information if stated. |
| Common values | Classification list | Recommended | `sustainability`, `cooperation`, `self-sufficiency` | Extract stated shared principles. |
| Community activities | Classification list | Recommended | `shared meals`, `circles`, `gardening days`, `workshops` | Focus on recurring activities. |
| Community management | Classification list | Optional | `consensus`, `host-led`, `self-organized` | Extract governance/coordination style if visible. |
| Community integration | Classification list | Recommended | `guests join work mornings`, `optional community events` | Capture how visitors participate in the community. |
| Inhabitants images | Image URL list | Optional | `https://example.org/community.jpg` | Extract respectful people/community images only when clearly part of listing presentation. |

## 7. Communal Household And Food

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Shared household relevant | Boolean / relevance score | Required | `true` | Determine whether nomads share kitchen, meals, cleaning, or household life. |
| Communal household description | Text | Recommended | `Guests share a vegetarian kitchen and participate in cleaning rotas.` | Capture practical household expectations. |
| Household design | Classification list | Recommended | `shared kitchen`, `private units`, `common house` | Extract how living spaces are arranged. |
| Eating together | Number/range | Recommended | `5 meals per week` | Extract frequency of shared meals. |
| Cooking | Classification list | Recommended | `self-catering`, `rotating cooking teams`, `meals included` | Describe cooking arrangements. |
| Food supply | Classification list | Recommended | `organic garden`, `local market`, `included meals` | Capture where food comes from and what is included. |
| Local food | Percentage/score | Recommended | `70% local food` | Extract concrete percentage if stated; otherwise summarize qualitatively. |
| Meal participation | Classification/list | Optional | `optional dinners`, `mandatory lunch shifts` | Extract expectations around meals. |
| Communal household images | Image URL list | Optional | `https://example.org/kitchen.jpg` | Prioritize kitchen, dining, common house, and shared facilities. |

## 8. Digital Work Infrastructure

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Remote work infrastructure provided | Boolean / relevance score | Required | `true` | Determine whether the site is suitable for remote work. |
| Digital description | Text | Recommended | `Fiber internet is available in the coworking room and common house.` | Extract the practical remote-work setup. |
| Internet connection | Classification list | Required if remote-work focused | `fiber`, `4G backup`, `Starlink` | Capture connection type. |
| Internet speed | Number with unit | Recommended | `100 Mbps down / 40 Mbps up` | Extract download/upload speed if stated. |
| Internet reliability | Classification/list | Recommended | `stable`, `backup available`, `weather dependent` | Do not infer reliability from speed alone. |
| Coworking area size | Integer/string | Optional | `40 m2` | Extract size if visible. |
| Coworking capacity | Integer | Recommended | `12 desks` | Extract number of desks or people supported. |
| Coworking access | Classification/list | Recommended | `24/7`, `included`, `extra fee`, `quiet hours` | Capture access rules. |
| Equipment | Classification list | Optional | `monitors`, `standing desks`, `printer`, `meeting room` | Extract work equipment and facilities. |
| Digital images | Image URL list | Recommended | `https://example.org/coworking.jpg` | Prioritize workspace, desks, meeting rooms, router/connectivity visuals. |

## 9. Skills, Learning, Volunteering, And Work Exchange

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Skills/work exchange relevant | Boolean / relevance score | Required | `true` | Determine whether the place offers skill sharing, training, volunteering, jobs, or work exchange. |
| Skills description | Text | Recommended | `Volunteers help with gardening, maintenance, and community events.` | Extract opportunities and expectations. |
| Skill offering | Classification list | Recommended | `workshops`, `mentoring`, `hands-on learning` | Capture what participants can learn or receive. |
| Skill requests | Classification list | Recommended | `carpentry`, `gardening`, `social media`, `cooking` | Capture skills the project asks for. |
| Skills images | Image URL list | Optional | `https://example.org/workshop.jpg` | Extract workshop, work exchange, training, or activity images. |

### Repeatable Skill Group

Use this group for each explicit skill offered, requested, taught, or promoted.

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Category | Classification | Recommended | `permaculture`, `construction`, `hospitality` | Match the skill area. |
| Description | Text | Recommended | `Help build greywater systems and learn ecological design.` | Capture what the skill involves. |
| Level | Classification | Optional | `beginner`, `intermediate`, `expert needed` | Extract skill level or experience requirements. |
| Promote | Boolean/classification | Optional | `featured workshop` | Capture whether the site promotes this skill as a highlighted offer. |
| Images | Image URL list | Optional | `https://example.org/permaculture-course.jpg` | Extract images tied to this skill/activity. |

## 10. Retreats, Wellness, And Spiritual Activities

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Retreats or wellness offered | Boolean / relevance score | Required | `true` | Determine whether retreats, wellness, spirituality, yoga, meditation, or healing activities are part of the offer. |
| Retreat description | Text | Recommended | `Seasonal yoga and silent meditation retreats are hosted on site.` | Extract the nature of the retreat/wellness offer. |
| Retreat opportunities | Classification list | Recommended | `yoga`, `meditation`, `therapy`, `sauna`, `ceremony` | Capture concrete opportunities. |
| Retreat images | Image URL list | Optional | `https://example.org/yoga-hall.jpg` | Extract retreat spaces, practice areas, wellness facilities, or event images. |

## 11. Adventure And Outdoor Activities

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Adventure experiences offered | Boolean / relevance score | Required | `true` | Determine whether outdoor/adventure experiences are part of the listing. |
| Adventures description | Text | Recommended | `The area offers hiking, climbing, canyoning, and mountain biking.` | Extract the activity context and access. |
| Adventure opportunities | Classification list | Recommended | `hiking`, `climbing`, `surfing`, `kayaking` | Capture specific activities. |
| Role in adventure activities | Classification/list | Optional | `guided`, `self-organized`, `partner providers` | Distinguish hosted from nearby/self-guided activities. |
| Adventure images | Image URL list | Optional | `https://example.org/climbing.jpg` | Extract images of actual outdoor activities or nearby nature. |

### Repeatable Adventure Group

Use this group when the website lists specific activities or excursions.

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Adventure | Classification/string | Recommended | `guided canyon hike` | Name the activity. |
| Offer | Classification/string | Optional | `included weekly`, `paid excursion`, `nearby self-guided` | Capture the commercial/practical offer. |
| Info | Text | Optional | `Suitable for beginners; transport included.` | Extract logistics, difficulty, seasonality, and requirements. |

## 12. Sustainability And Local Impact

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Sustainability/impact relevant | Boolean / relevance score | Required | `true` | Determine whether the project actively presents ecological or social impact practices. |
| Impact description | Text | Recommended | `The project uses solar energy, compost toilets, and local food sourcing.` | Extract meaningful practices, not generic green claims. |
| Eco practices | Classification list | Recommended | `solar power`, `compost toilets`, `water reuse`, `permaculture` | Capture concrete infrastructure and behavior. |
| Renewable energy | Percentage/score | Recommended | `80% solar` | Extract percentage if stated. |
| Self sufficiency | Percentage/score | Optional | `partly self-sufficient in vegetables` | Extract concrete food/energy/water independence where available. |
| Local economy | Percentage/score | Optional | `60% of spending goes to local suppliers` | Capture local sourcing/labor/economic flow if stated. |
| Local impact | Classification/list | Recommended | `local employment`, `community workshops`, `regional suppliers` | Extract social/economic contribution. |
| Flying impact | Number/text | Optional | `airport pickup discouraged`, `CO2 contribution offered` | Capture travel-impact messaging if visible. |
| Impact images | Image URL list | Optional | `https://example.org/solar-panels.jpg` | Extract images of gardens, infrastructure, energy, water, or local-impact work. |

## 13. Audience And Participant Fit

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| Audience composition relevant | Boolean / relevance score | Required | `true` | Determine whether the listing targets specific groups or participant types. |
| Audience description | Text | Recommended | `Best suited for independent adults interested in communal living and sustainability.` | Extract who the place is for. |
| Audience criteria | Classification/list | Recommended | `minimum 2-week stay`, `community-minded`, `no party tourism` | Capture requirements, expectations, exclusions, or screening criteria. |
| Audience groups | Classification list | Recommended | `digital nomads`, `families`, `volunteers`, `retreat guests` | Extract target groups. |
| Typical age range of participants | Range | Optional | `25-45` | Extract only if stated or clearly published. |
| Typical origin | Classification/list | Optional | `international`, `mostly Europe`, `local visitors` | Capture origin if mentioned. |
| Typical number of participants | Range/integer | Optional | `8-15` | Extract group size or attendance if stated. |
| Typical number of visiting children | Range/integer | Optional | `0-3` | Extract visitor children range if visible. |
| Audience images | Image URL list | Optional | `https://example.org/group.jpg` | Extract participant/group images when clearly part of the public listing. |

## 14. Media Collection

| Field | Value type | Priority | Example | AI extraction notes |
|---|---|---:|---|---|
| General images | Image URL list | Required | `https://example.org/main-house.jpg` | Collect main listing images showing the place, not decorative icons or logos. |
| Nomad community images | Image URL list | Recommended | `https://example.org/nomads-dinner.jpg` | Images showing visitors/nomads/community life. |
| Hosting images | Image URL list | Recommended | `https://example.org/bedroom.jpg` | Rooms, lodging, bathrooms, guest facilities. |
| Digital images | Image URL list | Recommended | `https://example.org/coworking-space.jpg` | Coworking and remote-work infrastructure. |
| Impact images | Image URL list | Optional | `https://example.org/garden.jpg` | Sustainability or local impact. |
| Skills images | Image URL list | Optional | `https://example.org/workshop.jpg` | Training, workshops, work exchange. |
| Retreat images | Image URL list | Optional | `https://example.org/yoga-platform.jpg` | Retreat/wellness/spiritual activities. |
| Adventure images | Image URL list | Optional | `https://example.org/hiking.jpg` | Outdoor/adventure context. |

Image extraction rules:

- Prefer absolute image URLs.
- Prefer original or high-resolution images over thumbnails.
- Exclude icons, logos, background textures, maps, avatars, tracking pixels, and decorative stock imagery unless they clearly represent the listing.
- Keep alt text, caption, and nearby heading when available.

## 15. Extraction Guardrails

- Do not invent values that are not on the source site.
- If a value is inferred, mark confidence as low and explain the inference in the source excerpt or notes.
- Preserve currencies, units, date ranges, and qualifiers.
- Normalize classifications only after extraction; keep the original source wording in source text excerpts.
- For repeatable groups, create one item per explicit offer, price, skill, or activity.
- If information appears inconsistent across pages, keep the newest or most specific page and record the conflicting source in notes.
