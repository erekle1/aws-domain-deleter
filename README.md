# AWS Domain Tools

A comprehensive PHP toolkit for managing AWS Route 53 domains - batch deletion and contact updates.

## ⚠️ Important Safety Warning

This script can **permanently delete hosted zones** and **update domain contact information**. These actions cannot be undone. Always test with `--dry-run` first!

## Features

### Domain Deletion
- ✅ Batch deletion of hosted zones from CSV file
- 🔍 Dry-run mode to preview actions without making changes
- 🛡️ Safety confirmation prompt (unless using `--force`)
- 🔧 AWS connection testing before operations
- 📊 Detailed execution summary
- 🚫 Automatic cleanup of DNS records before zone deletion
- ⏭️ Graceful handling of non-existent domains
- 🔑 Support for AWS session tokens (temporary credentials)
- 🌐 Optional domain registration processing (disable auto-renewal)
- 🗂️ Separate control for hosted zones vs domain registrations

### Domain Contact Updates (NEW!)
- 📞 Batch update of domain contact information
- 👤 Update admin, registrant, and technical contacts
- 📋 CSV-based domain selection with per-contact-type control
- 🔍 Dry-run mode for contact updates
- 📊 Detailed update summary and error reporting
- 🛡️ Safety confirmation for contact changes
- 📄 JSON-based contact information management

## Prerequisites

1. **PHP 8.3+** with Composer
2. **AWS credentials** with Route 53 permissions (including session token support)
3. **Domains list** in CSV format

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/erekle1/aws-domain-tools.git
   cd aws-domain-tools
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Configure AWS credentials:

   **Environment variables take precedence over config file settings.**

   **Method A: Environment Variables (Recommended)**
   ```bash
   export AWS_ACCESS_KEY_ID="your_access_key"
   export AWS_SECRET_ACCESS_KEY="your_secret_key"
   export AWS_SESSION_TOKEN="your_session_token"  # Optional: for temporary credentials
   export AWS_DEFAULT_REGION="eu-central-1"
   ```

   **Method B: .env File (Alternative)**
   Copy `env.example` to `.env` and customize:
   ```bash
   cp env.example .env
   # Edit .env with your credentials
   ```

   **Method C: Config File (Fallback)**
   Edit `src/config/aws_config.php` if not using environment variables:
   ```php
   return [
       'aws_access_key_id' => 'YOUR_ACCESS_KEY',
       'aws_secret_access_key' => 'YOUR_SECRET_KEY',
       // ... environment variables will override these
   ];
   ```

   **Method D: AWS CLI Profile**
   If you have AWS CLI configured, the script will use your default profile automatically.

   💡 **Tip**: Check `env.example` for all available environment variables including deletion control options.

3. Add domains to `domains.csv` (one domain per line):
   ```
   example.com
   test.com
   another-domain.org
   ```

## Configuration Options

You can control script behavior via **environment variables** (recommended) or config file:

### Environment Variables (Recommended)
```bash
# Deletion control
export DELETE_HOSTED_ZONES="true"        # Delete Route 53 hosted zones
export DELETE_DOMAIN_REGISTRATIONS="false"  # Process domain registrations
export PERMANENTLY_DELETE_DOMAINS="false"   # DANGEROUS! Permanently delete domains

# AWS settings
export AWS_USE_INSTANCE_PROFILE="false"  # Use EC2 instance profile
export AWS_CREDENTIAL_TIMEOUT="1"        # Credential provider timeout
```

### Config File (Fallback)
Edit `config/aws_config.php` if not using environment variables:
```php
// Domain deletion settings
'delete_hosted_zones' => true,         // Delete Route 53 hosted zones
'delete_domain_registrations' => false, // Process domain registrations
'permanently_delete_domains' => false,  // DANGEROUS! Permanently delete domains
```

### Important Notes:
- **Environment variables override config file settings**
- **Hosted Zone Deletion**: Completely removes DNS zones from Route 53
- **Domain Registration Processing**: Disables auto-renewal and provides transfer instructions  
- Set `DELETE_DOMAIN_REGISTRATIONS="true"` only if you want to process registered domains

## Contact Update Configuration

### Contact Information File (`contacts.json`)
Create a `contacts.json` file in the project root with your contact information:

```json
{
  "admin_contact": {
    "firstName": "John",
    "lastName": "Doe",
    "contactType": "PERSON",
    "organizationName": "Your Organization",
    "addressLine1": "123 Main Street",
    "city": "Your City",
    "state": "ST",
    "countryCode": "US",
    "zipCode": "12345",
    "phoneNumber": "+1.5551234567",
    "email": "admin@yourdomain.com",
    "extraParams": []
  },
  "registrant_contact": {
    "firstName": "Jane",
    "lastName": "Smith",
    "contactType": "PERSON",
    "organizationName": "Your Organization",
    "addressLine1": "123 Main Street",
    "city": "Your City",
    "state": "ST",
    "countryCode": "US",
    "zipCode": "12345",
    "phoneNumber": "+1.5551234567",
    "email": "registrant@yourdomain.com",
    "extraParams": []
  },
  "tech_contact": {
    "firstName": "Tech",
    "lastName": "Support",
    "contactType": "PERSON",
    "organizationName": "Your Organization",
    "addressLine1": "123 Main Street",
    "city": "Your City",
    "state": "ST",
    "countryCode": "US",
    "zipCode": "12345",
    "phoneNumber": "+1.5551234567",
    "email": "tech@yourdomain.com",
    "extraParams": []
  }
}
```

### Domains to Update File (`domains_to_update.csv`)
Create a CSV file specifying which domains to update and which contact types:

```csv
domain_name,update_admin,update_registrant,update_tech
example.com,true,true,true
test-domain.com,false,true,false
another-domain.org,true,false,true
```

**CSV Columns:**
- `domain_name`: The domain to update
- `update_admin`: `true` to update admin contact, `false` to skip
- `update_registrant`: `true` to update registrant contact, `false` to skip  
- `update_tech`: `true` to update tech contact, `false` to skip

### ⚠️ PERMANENT DOMAIN DELETION (EXTREMELY DANGEROUS!)
- **`PERMANENTLY_DELETE_DOMAINS="true"`**: Uses AWS `deleteDomain` API to **IRREVERSIBLY** delete domain registrations
- **NO REFUNDS**: You will not receive any refund for deleted domain costs
- **IMMEDIATE RELEASE**: Domains are released back to public registry and can be registered by anyone
- **NO RECOVERY**: Once deleted, domains cannot be recovered
- **USE WITH EXTREME CAUTION**: Only enable for domains you absolutely never want again

## Usage

### Domain Deletion

#### Safe Testing (Recommended First Step)
```bash
php aws-domain-manager.php --delete-domains --dry-run
```
This shows what would be deleted without making any changes.

#### Interactive Deletion
```bash
php aws-domain-manager.php --delete-domains
```
This will:
1. Test AWS connection
2. Show domains to be deleted
3. Ask for confirmation
4. Delete hosted zones

#### Force Deletion (No Confirmation)
```bash
php aws-domain-manager.php --delete-domains --force
```

### Domain Contact Updates (NEW!)

#### Preview Contact Updates
```bash
php aws-domain-manager.php --update-contacts --admin-contact --tech-contact --dry-run
```

#### Update Admin and Tech Contacts
```bash
php aws-domain-manager.php --update-contacts --admin-contact --tech-contact
```

#### Update All Contact Types
```bash
php aws-domain-manager.php --update-contacts --admin-contact --registrant-contact --tech-contact
```

#### Force Contact Updates (No Confirmation)
```bash
php aws-domain-manager.php --update-contacts --admin-contact --force
```
```
⚠️ **Use with extreme caution!** Skips confirmation prompt.

## Command Line Options

### Operations
- `--delete-domains`: Delete domain hosted zones and registrations
- `--update-contacts`: Update domain contact information

### Common Options
- `--dry-run`: Preview actions without making changes
- `--force`: Skip confirmation prompt (dangerous!)
- `--help`, `-h`: Show help message

### Contact Update Options
- `--admin-contact`: Update admin contact for domains
- `--registrant-contact`: Update registrant contact for domains
- `--tech-contact`: Update technical contact for domains

## What the Script Does

### Hosted Zone Deletion (default: enabled)
1. **Tests AWS connection** to ensure credentials work
2. **Reads domains** from the CSV file
3. **Finds hosted zones** for each domain in Route 53
4. **Deletes DNS records** (except NS and SOA)
5. **Deletes the hosted zone** itself

### Domain Registration Processing (default: disabled)
1. **Checks if domains are registered** with Route 53 Domains
2. **Disables auto-renewal** to prevent automatic charges
3. **Provides transfer instructions** for complete domain removal
4. **Note**: AWS doesn't allow direct domain deletion - you must transfer out manually

### Contact Update Processing (NEW!)
1. **Loads contact information** from `contacts.json`
2. **Reads domain list** from `domains_to_update.csv`
3. **Tests AWS connection** to Route 53 Domains service
4. **Updates specified contact types** for each domain
5. **Provides detailed summary** of successful/failed updates
6. **Supports dry-run mode** to preview changes without applying them

### Final Steps
6. **Provides comprehensive summary** of all operations

## Output Example

```
🔧 Testing AWS connection...
✅ AWS connection successful

Found 3 domains to process:
- example.com
- test.com
- another-domain.org

⚠️  WARNING: This will permanently delete 3 hosted zones from AWS Route 53!
This action cannot be undone. Are you sure you want to continue? (yes/no): yes

Processing domain: **example.com**
-> Found Hosted Zone ID: Z1234567890
--> Deleting record: www.example.com (A)
--> All non-essential records deleted successfully.
✅ Successfully deleted Hosted Zone for 'example.com' (ID: Z1234567890).

============================================
📊 EXECUTION SUMMARY
============================================
Total domains processed: 3
✅ Successful deletions: 2
❌ Failed deletions: 0
⏭️  Skipped domains: 1

Script finished.
```

## Security Best Practices

1. **Never commit** `aws_config.php` to version control
2. **Use IAM users** with minimal required permissions
3. **Always test with `--dry-run`** first
4. **Double-check domain list** before running
5. **Keep backups** of important DNS configurations

## Required AWS Permissions

### For Hosted Zone Deletion (always required):
- `route53:ListHostedZones`
- `route53:ListHostedZonesByName`
- `route53:ListResourceRecordSets`
- `route53:ChangeResourceRecordSets`
- `route53:DeleteHostedZone`

### For Domain Registration Processing (only if enabled):
- `route53domains:GetDomainDetail`
- `route53domains:DisableDomainAutoRenew`
- `route53domains:ListDomains` (optional, for bulk operations)

### For Permanent Domain Deletion (DANGEROUS - only if enabled):
- `route53domains:DeleteDomain` ⚠️ **IRREVERSIBLE OPERATION**

**Note**: Route 53 Domains permissions are only available in `us-east-1` region.

## Troubleshooting

### "AWS connection failed"
- Check your AWS credentials in `src/config/aws_config.php`
- Verify your AWS region is correct
- Ensure your AWS user has Route 53 permissions
- If using temporary credentials, verify your session token is not expired
- Try setting `use_instance_profile` to `false` in config if not on EC2

### "Hosted Zone not found"
- The domain doesn't have a hosted zone in Route 53
- Check if the domain is spelled correctly
- The script will skip these domains automatically

### "Error deleting domain"
- The hosted zone might have dependent resources
- Some AWS errors require manual intervention
- Check the AWS console for more details

## File Structure

```
aws-domain-deleter/
├── aws-domain-manager.php    # Main domain management script (v3.0 - supports both deletion and contact updates)
├── domains.csv             # List of domains to delete (213 domains ready)
├── src/                    # Source code (object-oriented architecture)
│   ├── Application.php     # Main application orchestrator
│   ├── AWS/
│   │   ├── CredentialsManager.php  # AWS credentials handling
│   │   └── ClientFactory.php       # AWS client factory
│   └── Services/
│       ├── DomainManager.php       # Domain validation & loading
│       ├── Route53Service.php      # Route 53 hosted zone operations
│       ├── Route53DomainsService.php # Route 53 domain registration operations
│       └── UserInterface.php       # User interaction & display
├── config/
│   ├── aws_config.php      # AWS credentials & settings
│   └── helpers.php         # Helper functions
├── vendor/                 # Composer dependencies
├── composer.json           # PHP dependencies (latest AWS SDK)
├── env.example             # Environment variables setup guide
└── README.md              # This documentation
```

## Support

If you encounter issues:
1. Run with `--dry-run` to see what would happen
2. Check AWS console for hosted zone status
3. Verify your AWS permissions
4. Review the error messages in the output

## Author

**Erekle Kereselidze**
- GitHub: [@erekle1](https://github.com/erekle1)
- Email: erekle.kereselidze2@gmail.com

## Contributing

Contributions are welcome! Please read the [CONTRIBUTING.md](CONTRIBUTING.md) guide for details on how to contribute to this project.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
