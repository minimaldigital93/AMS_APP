# Fiscal Period Management - Quick Start Guide

## 🚀 Get Started in 5 Minutes

### Step 1: Access the System
1. Log in as an admin user
2. Navigate to **Admin Dashboard**
3. Click **Fiscal Periods** (or visit `/admin/fiscalperiod`)

### Step 2: Create Your First Fiscal Period
1. Click **Create New Fiscal Period**
2. Fill in the form:
   - **Name**: "Fiscal Year 2025-2026" (or your period name)
   - **Opening Date**: January 1, 2025
   - **Closing Date**: December 31, 2025
   - **Opening Balance**: $0 (or your starting balance)
3. Click **Create Fiscal Period**

### Step 3: Add Balance Sheet Items
On the Balance Sheet page:
1. Select **Item Type**: Asset, Liability, or Equity
2. Select **Sub Type**: Choose from dropdown (Cash, Equipment, Loans, etc.)
3. Enter **Item Name**: Descriptive name (e.g., "Cash in Bank")
4. Enter **Amount**: Dollar amount
5. Select **As Of Date**: When this item applies
6. Click **Add Item**

Repeat for all your assets, liabilities, and equity items.

### Step 4: Monitor Your Balance
Watch the **Summary Panel** on the right:
- ✓ **Balance Check**: Should show "Balanced"
- If ✗ **Unbalanced**: Add missing items to balance Assets = Liabilities + Equity

### Step 5: Close the Fiscal Period
When your period ends:
1. Click **Set Closing Balance**
2. Review the suggested closing balance
3. Confirm the amount
4. Click **Close Fiscal Period**

### Step 6: Generate Reports
View your complete balance sheet:
1. Go to **Reports & Exports**
2. Download as CSV or print as PDF
3. Share with stakeholders

---

## 📚 Complete Workflow

```
Create Period → Add Items → Monitor Balance → Close Period → View Reports
```

## 🎯 Common Tasks

### How to add a cash account?
1. Click **Add Item** in Balance Sheet
2. Item Type: **Asset**
3. Sub Type: **Cash**
4. Name: "Cash in Bank"
5. Amount: [your amount]
6. Date: [today's date]

### How to record a loan?
1. Item Type: **Liability**
2. Sub Type: **Loans**
3. Name: "Bank Loan - $50,000"
4. Amount: 50000
5. Reference: "Loan Agreement #123"

### How to export data?
1. Go to **Reports & Exports**
2. Click **Download CSV**
3. File opens in Excel automatically
4. Or click **Print Report** for PDF

### How to change period dates?
1. Click **Edit Period** (only if open)
2. Update the dates
3. Click **Update Fiscal Period**

### How to add more items?
1. Stay on **Balance Sheet Items** page
2. Use the form at the top
3. Add as many items as needed
4. Watch the summary update in real time

---

## ⚠️ Important Rules

| Action | Open Period | Closed Period |
|--------|---|---|
| Add Items | ✓ Yes | ✗ No |
| Edit Items | ✓ Yes | ✗ No |
| Delete Items | ✓ Yes | ✗ No |
| Edit Dates | ✓ Yes | ✗ No |
| View Items | ✓ Yes | ✓ Yes |
| View Reports | ✓ Yes | ✓ Yes |
| Export Data | ✓ Yes | ✓ Yes |
| Close Period | ✓ Yes | — |
| Delete Period | ✓ Yes | ✗ No |

---

## 🔐 Who Can Access?

- ✓ **Admin Users**: Full access
- ✗ **Supervisors**: No access
- ✗ **Tenants**: No access

Admin role required for all fiscal period operations.

---

## 📊 The Balance Sheet Equation

Your fiscal period is **BALANCED** when:

```
ASSETS = LIABILITIES + EQUITY
```

Example:
```
$100,000 = $60,000 + $40,000  ✓ Balanced
```

If **unbalanced**:
- Add missing assets
- Add missing liabilities
- Adjust equity to balance

---

## 🗂️ Item Categories

### ASSETS (What you own)
- Cash in bank and accounts
- Accounts receivable (money owed to you)
- Property and equipment
- Other valuables

### LIABILITIES (What you owe)
- Accounts payable (money you owe)
- Loans and mortgages
- Deposits held from tenants
- Other obligations

### EQUITY (Owner's stake)
- Capital investment
- Retained earnings
- Other reserves

---

## 📱 Navigation Map

```
Home
└── Admin Dashboard
    └── Fiscal Periods
        ├── Create New → [Fill Form] → Balance Sheet Items
        ├── [View Period] → Show Details
        │                → Edit Period
        │                → Balance Sheet Items
        │                → Set Closing Balance → Close Period
        │                → Reports & Exports
        │                   ├── View Report
        │                   ├── Download CSV
        │                   └── Print PDF
        │
        └── [All Periods List]
            └── View | Edit | Reports
```

---

## 🐛 Troubleshooting

### "Balance is unbalanced"
Check: Assets = Liabilities + Equity?
- Add missing items
- Verify amounts are correct
- Check item types are correct

### "Cannot edit period"
Period may be closed. Only open periods can be edited.

### "Date is invalid"
Date must be within the fiscal period range:
- After opening date
- Before closing date

### "Cannot find my period"
- Check you're logged in as admin
- Visit `/admin/fiscalperiod` directly
- Contact system administrator

---

## 💾 Data Backup

Always backup your data:
1. Export CSV monthly
2. Print PDF for records
3. Save spreadsheets with version dates
4. Keep copies in secure location

---

## ✅ Checklist Before Closing

Before closing a fiscal period:
- [ ] All assets recorded
- [ ] All liabilities recorded
- [ ] All equity items recorded
- [ ] Balance sheet is balanced (✓)
- [ ] All amounts verified
- [ ] All dates correct
- [ ] Reference numbers added
- [ ] Notes added for unusual items
- [ ] CSV exported and saved
- [ ] Reports verified

---

## 📞 Support Resources

| Need | Location |
|------|----------|
| User Guide | `FISCAL_PERIOD_GUIDE.md` |
| Technical Details | `FISCAL_PERIOD_IMPLEMENTATION.md` |
| Integration Help | `FISCAL_PERIOD_NAVIGATION.md` |
| Direct Access | `/admin/fiscalperiod` |

---

## 🎓 Learning Path

**Beginner:**
1. Read this Quick Start Guide
2. Create your first period
3. Add a few balance items
4. Watch balances update live

**Intermediate:**
1. Create multiple periods
2. Manage all item types
3. Close periods properly
4. Export reports

**Advanced:**
1. Optimize period structure
2. Create templates
3. Analyze trends
4. Share reports with stakeholders

---

## ⏱️ Typical Timeline

| Task | Time |
|------|------|
| Create period | 5 min |
| Add 10 items | 10 min |
| Balance sheet | 5 min |
| Close period | 3 min |
| Export report | 1 min |
| **Total** | **25 min** |

---

## 🎯 Pro Tips

1. **Add items regularly** - Don't wait until end of period
2. **Use reference numbers** - Invoice/document numbers for tracking
3. **Add notes** - Explain unusual or large items
4. **Export monthly** - Create backup copies regularly
5. **Double-check balance** - Verify equation is satisfied
6. **Keep periods short** - Quarterly or monthly is better than yearly
7. **Review before closing** - Once closed, it's read-only
8. **Plan next period** - Have next period ready when current closes

---

## 🔄 Sample Workflow by Date

**January 2025**
- Create period: "Q1 2025" (Jan 1 - Mar 31)
- Opening balance: $100,000

**January - March 2025**
- Add items as they occur
- Monitor balance sheet
- Maintain summary in notes

**March 31, 2025**
- Final review
- Balance check: ✓ Balanced
- Close period
- Export report

**April 1, 2025**
- Create Q2 2025 period
- Opening balance: Previous Q closing balance
- Continue...

---

**Ready to get started?** 

👉 [Go to Fiscal Periods](/admin/fiscalperiod)

---

**Last Updated**: February 2026  
**Version**: 1.0
