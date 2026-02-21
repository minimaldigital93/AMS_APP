# Fiscal Period Management System - User Guide

## Overview

The Fiscal Period Management System allows you to manage your business's financial accounting cycle by creating fiscal periods, recording balance sheet items, and generating comprehensive financial reports.

## Features

- **Create Fiscal Periods**: Define the opening and closing dates of your accounting periods
- **Balance Sheet Management**: Record and manage assets, liabilities, and equity items
- **Opening/Closing Balances**: Set opening balance and close fiscal periods with final balances
- **Financial Reporting**: View and export balance sheet reports
- **CSV Export**: Export data for use in Excel or other accounting software
- **Balance Verification**: Automatic calculation and verification of balance sheet equations

## Step-by-Step Guide

### 1. Create a New Fiscal Period

1. Navigate to **Admin → Fiscal Periods**
2. Click **Create New Fiscal Period**
3. Enter the following information:
   - **Period Name**: e.g., "Fiscal Year 2025-2026" or "Q1 2026"
   - **Opening Date**: Start date of your accounting period
   - **Closing Date**: End date of your accounting period
   - **Opening Balance**: Initial balance at the start of this period
4. Click **Create Fiscal Period**
5. You will be automatically directed to the Balance Sheet management page

### 2. Add Balance Sheet Items

After creating a fiscal period:

1. On the **Balance Sheet Items** page, fill in the **Add Balance Sheet Item** form:
   - **Item Type**: Choose one of three types:
     - **Asset**: Items of value owned by your business (cash, equipment, property, etc.)
     - **Liability**: Debts or obligations (loans, accounts payable, deposits held, etc.)
     - **Equity**: Owner's investment and retained earnings
   
   - **Sub Type**: Select the category (e.g., Cash, Equipment, Loans)
   - **Item Name**: Descriptive name for the item
   - **Description**: Additional details (optional)
   - **Amount**: Dollar amount of the item
   - **As Of Date**: The date this item applies to (must be within the fiscal period)
   - **Reference Number**: Invoice or document number (optional)
   - **Notes**: Any additional notes (optional)

2. Click **Add Item**

### 3. Monitor Balance Sheet

The **Summary** panel shows:
- **Total Assets**: Sum of all asset items
- **Total Liabilities**: Sum of all liability items
- **Total Equity**: Sum of all equity items
- **Balance Check**: Visual indicator if the balance sheet equation is satisfied

**Balance Sheet Equation**: Assets = Liabilities + Equity

### 4. Set Closing Balance

When your fiscal period is complete:

1. Go to the fiscal period and click **Set Closing Balance**
2. Review the calculated values:
   - Assets, Liabilities, and Equity totals
   - Suggested Closing Balance (calculated automatically)
   - Net Change from Opening Balance
3. Enter the **Closing Balance**
4. Review the period summary
5. Click **Close Fiscal Period**

⚠️ **Important**: Once a period is closed, you cannot add or edit balance items for that period.

### 5. Generate & Export Reports

#### View Detailed Report
1. Go to **Reports & Exports**
2. Review the complete balance sheet with all items
3. Click **View & Print Report** to print or save as PDF

#### Export to CSV
1. Go to **Reports & Exports**
2. Click **Download CSV**
3. File downloads automatically (filename: `balance_sheet_{id}_{date}.csv`)
4. Open in Excel, Google Sheets, or your accounting software

### 6. Edit Fiscal Period

**Only for open periods:**

1. Click **Edit Period** on the fiscal period details page
2. Modify:
   - Period Name
   - Opening Date
   - Closing Date
   - Opening Balance
3. Click **Update Fiscal Period**

### 7. Delete Fiscal Period

**Only for open periods (no balance items):**

1. Navigate to fiscal period list
2. Find the period and click the delete action
3. Confirm deletion

⚠️ **Note**: You cannot delete periods with balance items or closed periods.

## Understanding Balance Sheet Structure

### Assets (What the business owns)
- **Cash**: Money in bank accounts
- **Accounts Receivable**: Money owed by customers
- **Property**: Land and buildings
- **Equipment**: Machinery, furniture, tools
- **Other Assets**: Investments, patents, etc.

**Total Assets** = What your business owns

### Liabilities (What the business owes)
- **Accounts Payable**: Money owed to suppliers
- **Loans**: Bank loans or mortgages
- **Deposits Held**: Security deposits from tenants
- **Other Liabilities**: Other obligations

**Total Liabilities** = What your business owes

### Equity (Owner's stake)
- **Capital**: Owner's initial investment
- **Retained Earnings**: Accumulated profits
- **Other Equity**: Additional owner contributions or reserves

**Total Equity** = Owner's net investment

## The Balance Sheet Equation

A balanced balance sheet follows this fundamental equation:

```
ASSETS = LIABILITIES + EQUITY
```

Example:
- Total Assets: $100,000
- Total Liabilities: $60,000
- Total Equity: $40,000

✓ Balanced: $100,000 = $60,000 + $40,000

## Reports

### Balance Sheet Report
Shows all items grouped by type with:
- Item names and descriptions
- Amounts for each item
- Dates and reference numbers
- Totals for each category
- Balance verification

### CSV Export Contents
- Complete item listing
- All reference information
- Summary calculations
- Compatible with spreadsheets and accounting software

## Tips & Best Practices

1. **Regular Updates**: Add balance items as transactions occur during the period
2. **Reference Numbers**: Use invoice or document numbers for traceability
3. **Descriptions**: Add notes to explain unusual items
4. **Date Accuracy**: Ensure all dates are within the fiscal period range
5. **Review Balance**: Monitor the balance check indicator - it should show ✓ Balanced
6. **Close on Time**: Close periods on the official fiscal end date
7. **Archive Exports**: Save CSV exports for historical records

## Fiscal Period Workflow

```
1. Create Fiscal Period
   ↓
2. Add Balance Sheet Items
   ↓
3. Monitor Balance
   ↓
4. Set Closing Balance
   ↓
5. Close Period (becomes read-only)
   ↓
6. View Reports & Export
   ↓
7. Create Next Period
```

## Common Scenarios

### Scenario 1: New Business Starting Fiscal Year
1. Create period with opening date (Jan 1) and closing date (Dec 31)
2. If no opening balance, set to $0
3. Add initial assets (cash, equipment)
4. Add initial liabilities (loans, deposits)
5. Balance should equal with initial equity

### Scenario 2: Quarterly Reporting
1. Create Q1 period (Jan 1 - Mar 31)
2. Add balance items throughout quarter
3. At end of quarter, set closing balance
4. Close period and export report
5. Create Q2 period (Apr 1 - Jun 30) with Q1 closing balance as Q2 opening balance

### Scenario 3: Mid-Year Review
1. Open the fiscal period (if still open)
2. Add any missing balance items
3. Review the balance check
4. Export report for review
5. Continue adding items until period close

## Troubleshooting

### "Balance is unbalanced"
- Check that all items are recorded
- Verify amounts are correct
- Ensure all asset/liability/equity items are included
- The equation Assets = Liabilities + Equity must be true

### Cannot modify period
- The period may be closed
- Closed periods are read-only
- Create a new period if you need to add more items

### Date validation error
- Ensure dates are within the fiscal period range
- The "As Of Date" must be between Opening and Closing dates

## Support

For issues or questions:
1. Review this guide
2. Check the balance sheet summary
3. Verify all items are correctly categorized
4. Contact your system administrator

---

**Version**: 1.0  
**Last Updated**: February 2026
