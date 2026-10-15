# DOC-096 — کاربرد CRM (مدیریت ارتباط با مشتری) 🤝

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-096 |
| سطح | حرفه‌ای |
| معماری | `hexagonal` |
| ماژول‌ها | core-user, crm, ticketing, notification, workflow, report |
| موتورها | rbac, audit, search, queue, mail |

---

## ۱. دامنه
مدیریت ارتباط با مشتری: سرنخ (Lead)، مخاطب (Contact)، فرصت فروش (Opportunity/Pipeline)،
فعالیت‌ها، تیکت پشتیبانی، کمپین و گزارش.

### ۱٫۱ Bounded Contextها
```
Lead & Contact │ Sales Pipeline │ Activity │ Support (Ticketing) │ Campaign │ Reporting
```

### ۱٫۲ Aggregateها
| Aggregate | Root | توضیح |
|---|---|---|
| Contact | `Contact` | مخاطب/مشتری |
| Lead | `Lead` | سرنخ فروش |
| Opportunity | `Opportunity` | فرصت فروش در pipeline |
| Activity | `Activity` | تماس/جلسه/ایمیل/یادداشت |
| Ticket | `Ticket` | تیکت پشتیبانی |
| Campaign | `Campaign` | کمپین بازاریابی |

## ۲. مدل داده
```sql
CREATE TABLE crm_contacts (
  id CHAR(26) PRIMARY KEY,
  first_name VARCHAR(100), last_name VARCHAR(100),
  company VARCHAR(190), title VARCHAR(100),
  email VARCHAR(190), mobile VARCHAR(20), phone VARCHAR(20),
  national_id VARCHAR(20),                 -- encrypted cast
  source VARCHAR(50),                      -- website|referral|campaign|import
  owner_id BIGINT UNSIGNED,                -- کارشناس مسئول
  status VARCHAR(20) DEFAULT 'active',
  tags JSON, custom_fields JSON,
  created_at DATETIME(3), updated_at DATETIME(3), deleted_at DATETIME(3),
  KEY idx_owner (owner_id), KEY idx_email (email)
);

CREATE TABLE crm_leads (
  id CHAR(26) PRIMARY KEY, contact_id CHAR(26), 
  title VARCHAR(255), value DECIMAL(18,4), currency CHAR(3),
  status VARCHAR(20) DEFAULT 'new',        -- new|qualified|converted|lost
  score INT DEFAULT 0,                     -- lead scoring
  owner_id BIGINT UNSIGNED, converted_to CHAR(26) NULL,  -- → opportunity
  created_at DATETIME(3)
);

CREATE TABLE crm_opportunities (
  id CHAR(26) PRIMARY KEY, contact_id CHAR(26), lead_id CHAR(26) NULL,
  title VARCHAR(255), value DECIMAL(18,4), currency CHAR(3) DEFAULT 'IRR',
  stage VARCHAR(30) DEFAULT 'prospecting', -- prospecting|qualification|proposal|negotiation|closed_won|closed_lost
  probability INT DEFAULT 0, expected_close_date DATE,
  owner_id BIGINT UNSIGNED, lost_reason VARCHAR(255) NULL,
  created_at DATETIME(3), updated_at DATETIME(3), closed_at DATETIME(3),
  KEY idx_stage (stage), KEY idx_owner (owner_id)
);

CREATE TABLE crm_activities (
  id CHAR(26) PRIMARY KEY, activity_type VARCHAR(20),  -- call|meeting|email|note|task
  subject VARCHAR(255), body TEXT,
  contact_id CHAR(26) NULL, opportunity_id CHAR(26) NULL, ticket_id CHAR(26) NULL,
  user_id BIGINT UNSIGNED, due_at DATETIME(3), completed_at DATETIME(3),
  created_at DATETIME(3),
  KEY idx_polymorph (contact_id, opportunity_id, ticket_id)
);

CREATE TABLE crm_tickets (
  id CHAR(26) PRIMARY KEY, ticket_number VARCHAR(32) UNIQUE,
  contact_id CHAR(26), subject VARCHAR(255), body TEXT,
  category VARCHAR(50), priority VARCHAR(20) DEFAULT 'normal',  -- low|normal|high|urgent
  status VARCHAR(20) DEFAULT 'open',       -- open|pending|resolved|closed
  assigned_to BIGINT UNSIGNED, 
  sla_due_at DATETIME(3), resolved_at DATETIME(3),
  created_at DATETIME(3), updated_at DATETIME(3),
  KEY idx_status (status, priority), KEY idx_assigned (assigned_to)
);

CREATE TABLE crm_ticket_replies (
  id CHAR(26) PRIMARY KEY, ticket_id CHAR(26), author_id BIGINT UNSIGNED,
  author_type VARCHAR(20),                 -- agent|customer
  body TEXT, attachments JSON, is_internal TINYINT(1) DEFAULT 0,
  created_at DATETIME(3)
);

CREATE TABLE crm_campaigns (
  id CHAR(26) PRIMARY KEY, name VARCHAR(190), type VARCHAR(30),  -- email|sms
  status VARCHAR(20), audience_query JSON, template TEXT,
  sent_count INT DEFAULT 0, scheduled_at DATETIME(3), sent_at DATETIME(3)
);
```

## ۳. جریان‌های اصلی

### ۳٫۱ Sales Pipeline (با Workflow)
```
Lead (new) → qualify → Opportunity (prospecting)
  → qualification → proposal → negotiation → closed_won / closed_lost
هر stage با probability و value → weighted forecast
```

### ۳٫۲ Lead Scoring
```php
final class LeadScoringService
{
    public function score(Lead $lead): int
    {
        $score = 0;
        $score += $lead->source === 'referral' ? 20 : 5;
        $score += $lead->contact()->hasCompany() ? 15 : 0;
        $score += $lead->value()->amount() > 100_000_000 ? 25 : 10;
        $score += $this->engagementScore($lead);   // بازکردن ایمیل، بازدید
        return \min(100, $score);
    }
}
```

### ۳٫۳ تیکت پشتیبانی با SLA
```
Ticket created → SLA timer starts (بر اساس priority)
  → assigned به کارشناس
  → reply → status=pending (منتظر مشتری)
  → resolve → status=resolved → (customer confirm) → closed
  → اگر SLA نقض → escalation (workflow)
```

## ۴. نقش‌ها
| نقش | مجوز |
|---|---|
| `sales-agent` | sرنخ/فرصت خود، فعالیت |
| `sales-manager` | تیم خود، گزارش، assign |
| `support-agent` | تیکت‌های assigned |
| `support-manager` | همهٔ تیکت‌ها، SLA |
| `marketing` | کمپین، مخاطب |
| `crm-admin` | همه |

## ۵. رویدادها
| رویداد | مصرف‌کننده |
|---|---|
| `crm.lead.created` | scoring, assignment, notification |
| `crm.lead.converted` | opportunity ایجاد |
| `crm.opportunity.stage_changed` | forecast update, notification |
| `crm.opportunity.won` | report, celebration, invoice |
| `crm.ticket.created` | SLA timer, assignment, notification |
| `crm.ticket.sla_breached` | escalation workflow |
| `crm.ticket.resolved` | satisfaction survey |

## ۶. گزارش‌ها (report engine)
- Pipeline value by stage
- Win/Loss rate
- Sales forecast (weighted)
- Agent performance
- Ticket resolution time / SLA compliance
- Lead source effectiveness
- Revenue by period

## ۷. یکپارچگی
| منبع | روش |
|---|---|
| فرم وب‌سایت | API → lead |
| ایمیل | mail parser → ticket |
| تلفن (VoIP) | webhook → activity |
| فروشگاه (shop) | customer → contact sync |
| شبکهٔ اجتماعی | webhook |

## ۸. تست E2E
| ID | سناریو |
|---|---|
| E2E-01 | lead → qualify → opportunity → won |
| E2E-02 | lead scoring |
| E2E-03 | ticket با SLA + escalation |
| E2E-04 | campaign email |
| E2E-05 | گزارش pipeline |
| E2E-06 | sync مشتری از shop |

## ۹. معیار پذیرش 🧪
- AC-CRM-01..06.
- AC-CRM-07: Workflow pipeline با forecast درست.
- AC-CRM-08: SLA breach → escalation خودکار.
