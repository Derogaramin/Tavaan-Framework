# DOC-101 — کاتالوگ مجوزها (Permission Catalog) 🔒🧠

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-101 |
| قالب کلید | `<module>.<resource>.<action>` |
| مرتبط | DOC-100 (نقش‌ها)، DOC-057 (RBAC) |

---

## ۱. قواعد نام‌گذاری مجوز 🔒

```
<module>.<resource>[.<sub-resource>].<action>

module   : شناسهٔ ماژول بدون prefix (shop, blog, crm, system)
resource : منبع (product, order, post, …)
action   : عمل (view, create, update, delete, + عمل‌های خاص)
```

### ۱٫۱ اکشن‌های استاندارد
| اکشن | معنا |
|---|---|
| `view` | مشاهدهٔ یک مورد |
| `view-any` | مشاهدهٔ فهرست |
| `create` | ایجاد |
| `update` | ویرایش |
| `delete` | حذف |
| `restore` | بازیابی (soft-deleted) |
| `force-delete` | حذف قطعی |
| `export` | خروجی گرفتن |
| `import` | ورود داده |
| `manage` | همهٔ اعمال بالا |

### ۱٫۲ Wildcard
```
shop.product.*      → همهٔ اکشن‌های محصول
shop.*              → همهٔ منابع فروشگاه
*                   → همه (فقط super-admin)
```

---

## ۲. مجوزهای سیستمی (system)

```
system.settings.view          system.settings.manage
system.modules.view           system.modules.install       system.modules.uninstall
system.users.view             system.users.create          system.users.update
system.users.delete           system.users.impersonate     system.users.reset-password
system.roles.view             system.roles.manage          system.roles.assign
system.permissions.view       system.permissions.sync
system.audit.view             system.audit.export          system.audit.verify
system.logs.view              system.logs.export           system.logs.clear
system.backup.create          system.backup.restore        system.backup.manage
system.cache.flush            system.config.manage
system.maintenance.toggle     system.debug                 system.cli.execute
system.tenants.manage         system.plugins.manage
system.health.view            system.metrics.view
```

---

## ۳. مجوزهای احراز هویت (auth)

```
auth.login                    auth.register                auth.logout
auth.password.change          auth.password.reset
auth.profile.view             auth.profile.update
auth.2fa.enable               auth.2fa.disable
auth.sessions.view            auth.sessions.revoke
auth.devices.view             auth.devices.revoke
auth.tokens.manage            auth.impersonate
```

---

## ۴. مجوزهای فروشگاه (shop)

```
shop.product.view             shop.product.view-any        shop.product.create
shop.product.update           shop.product.delete          shop.product.publish
shop.category.manage          shop.tag.manage
shop.inventory.view           shop.inventory.manage        shop.inventory.adjust
shop.cart.view                shop.cart.manage
shop.order.view               shop.order.view-any          shop.order.create
shop.order.update             shop.order.cancel            shop.order.refund
shop.order.status             shop.order.assign            shop.order.export
shop.discount.view            shop.discount.manage         shop.coupon.manage
shop.shipping.manage          shop.payment.view            shop.payment.refund
shop.report.sales             shop.report.products         shop.report.customers
shop.review.moderate          shop.vendor.manage
```

---

## ۵. مجوزهای محتوا (blog / news)

```
content.post.view             content.post.view-any        content.post.create
content.post.update           content.post.delete          content.post.publish
content.post.feature          content.category.manage      content.tag.manage
content.comment.view          content.comment.moderate     content.comment.delete
content.media.upload          content.media.manage         content.media.delete
content.page.manage           content.menu.manage
news.breaking.publish         news.editor.review           news.chief.publish
```

---

## ۶. مجوزهای LMS (lms)

```
lms.course.view               lms.course.view-any          lms.course.create
lms.course.update             lms.course.delete            lms.course.publish
lms.lesson.manage             lms.section.manage
lms.enrollment.view           lms.enrollment.create        lms.enrollment.manage
lms.progress.view             lms.progress.update
lms.quiz.create               lms.quiz.grade               lms.quiz.attempt
lms.certificate.issue         lms.certificate.verify       lms.certificate.manage
lms.instructor.manage         lms.category.manage
lms.report.progress           lms.report.sales
```

---

## ۷. مجوزهای فروم (forum)

```
forum.forum.view              forum.forum.manage
forum.thread.view             forum.thread.create          forum.thread.reply
forum.thread.moderate         forum.thread.lock            forum.thread.pin
forum.thread.move             forum.thread.delete
forum.post.view               forum.post.create            forum.post.update
forum.post.delete             forum.post.hide              forum.post.vote
forum.post.mark-solution      forum.post.flag
forum.user.ban                forum.user.mute
```

---

## ۸. مجوزهای CRM (crm)

```
crm.contact.view              crm.contact.view-any         crm.contact.create
crm.contact.update            crm.contact.delete           crm.contact.export
crm.contact.import            crm.contact.assign
crm.lead.view                 crm.lead.create              crm.lead.update
crm.lead.qualify              crm.lead.convert             crm.lead.assign
crm.opportunity.view          crm.opportunity.create       crm.opportunity.update
crm.opportunity.stage         crm.opportunity.won          crm.opportunity.lost
crm.activity.view             crm.activity.create          crm.activity.manage
crm.ticket.view               crm.ticket.create            crm.ticket.assign
crm.ticket.reply              crm.ticket.resolve           crm.ticket.close
crm.ticket.priority           crm.ticket.sla
crm.campaign.view             crm.campaign.create          crm.campaign.send
crm.report.pipeline           crm.report.sales             crm.report.agent
```

---

## ۹. مجوزهای ERP (erp)

```
erp.account.view              erp.account.manage
erp.voucher.view              erp.voucher.create           erp.voucher.approve
erp.voucher.post              erp.voucher.void
erp.inventory.view            erp.inventory.receive        erp.inventory.issue
erp.inventory.transfer        erp.inventory.adjust         erp.inventory.count
erp.procurement.request       erp.procurement.order        erp.procurement.approve
erp.procurement.receive
erp.sales.order               erp.sales.invoice            erp.sales.approve
erp.production.bom            erp.production.order         erp.production.complete
erp.hr.personnel.view         erp.hr.personnel.manage      erp.hr.payroll.process
erp.hr.payroll.approve        erp.hr.leave.approve
erp.asset.manage              erp.budget.manage
erp.report.financial          erp.report.trial-balance     erp.report.balance-sheet
erp.period.close              erp.period.reopen
```

---

## ۱۰. مجوزهای BPMS (bpms)

```
bpms.process.view             bpms.process.design          bpms.process.deploy
bpms.process.delete           bpms.instance.view           bpms.instance.start
bpms.instance.suspend         bpms.instance.terminate      bpms.instance.reassign
bpms.task.view                bpms.task.claim              bpms.task.complete
bpms.task.delegate            bpms.task.escalate
bpms.form.design              bpms.form.manage
bpms.monitor.view             bpms.report.kpi
```

---

## ۱۱. مجوزهای گزارش (report)

```
report.view                   report.create                report.run
report.export                 report.schedule              report.share
report.designer               report.dataset.manage
```

---

## ۱۲. ساختار ماشین‌خوان (برای Sync خودکار) 🧠

```yaml
# machine-readable: tavaan.permissions.v1
# این فهرست با `tvn rbac:sync` به دیتابیس منتقل می‌شود
permissions:
  system:
    group_fa: "سیستم"
    items:
      - {key: system.settings.view,   fa: "مشاهدهٔ تنظیمات", en: "View settings"}
      - {key: system.settings.manage, fa: "مدیریت تنظیمات", en: "Manage settings"}
      - {key: system.modules.install, fa: "نصب ماژول", en: "Install module"}
      - {key: system.users.manage,    fa: "مدیریت کاربران", en: "Manage users"}
      - {key: system.roles.manage,    fa: "مدیریت نقش‌ها", en: "Manage roles"}
      - {key: system.audit.view,      fa: "مشاهدهٔ ممیزی", en: "View audit"}
      # …
  shop:
    group_fa: "فروشگاه"
    items:
      - {key: shop.product.view,   fa: "مشاهدهٔ محصول", en: "View product"}
      - {key: shop.product.create, fa: "ایجاد محصول", en: "Create product"}
      - {key: shop.product.update, fa: "ویرایش محصول", en: "Update product"}
      - {key: shop.product.delete, fa: "حذف محصول", en: "Delete product"}
      - {key: shop.order.refund,   fa: "استرداد سفارش", en: "Refund order"}
      - {key: shop.report.sales,   fa: "گزارش فروش", en: "Sales report"}
      # …
  # crm, erp, lms, forum, bpms, content, auth, report …
```

---

## ۱۳. Sync خودکار از Manifest ماژول

🔒 هر ماژول مجوزهایش را در `module.json` → `permissions.items` اعلام می‌کند (DOC-022 §3).
در `tvn module:install` و `tvn rbac:sync`:
```php
final class PermissionSync
{
    public function syncAll(): SyncReport
    {
        $declared = $this->collectFromManifests();     // همهٔ ماژول‌ها
        $existing = Permission::pluck('key')->all();

        $added = $this->insertNew(\array_diff($declared, $existing));
        $removed = $this->softRemove(\array_diff($existing, $declared));

        return new SyncReport($added, $removed);
    }
}
```

---

## ۱۴. اعتبارسنجی مجوزها (`tvn rbac:check`)

| بررسی | خطا |
|---|---|
| کلید تکراری | `duplicate permission key` |
| کلید بدون ماژول معتبر | `unknown module prefix` |
| مجوز استفاده‌نشده در کد | warning (dead permission) |
| `Gate::define` بدون ثبت در کاتالوگ | error |
| مجوز ثبت‌شده بدون `Gate::define`/Policy | warning |

---

## ۱۵. آمار کاتالوگ
| ماژول | تعداد مجوز |
|---|---|
| system | ~35 |
| auth | ~15 |
| shop | ~35 |
| content/news | ~20 |
| lms | ~25 |
| forum | ~20 |
| crm | ~35 |
| erp | ~40 |
| bpms | ~20 |
| report | ~7 |
| **جمع** | **~۲۵۰** |

---

## ۱۶. معیار پذیرش 🧪
- AC-PERM-01: همهٔ مجوزهای این کاتالوگ در seed اولیه sync می‌شوند.
- AC-PERM-02: هر ماژول مجوزهایش را در manifest دارد.
- AC-PERM-03: `tvn rbac:check` بدون خطا.
- AC-PERM-04: کلید یکتا در کل سیستم.
- AC-PERM-05: ترجمهٔ fa و en برای همه.
