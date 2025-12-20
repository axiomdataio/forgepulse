# ForgePulse Vapor Deployment Checklist

**Print this checklist and check off items as you complete them**

---

## Pre-Deployment

### Local Setup
- [ ] Laravel 12+ application installed
- [ ] PHP 8.3+ configured
- [ ] Composer installed and updated
- [ ] Node.js and npm installed

### Accounts & Access
- [ ] Laravel Vapor account created
- [ ] Vapor CLI installed (`composer global require laravel/vapor-cli`)
- [ ] Logged into Vapor CLI (`vapor login`)
- [ ] AWS account with billing enabled
- [ ] Credit card added to Vapor account

### ForgePulse Installation
- [ ] Package installed (`composer require alizharb/forgepulse`)
- [ ] Configuration published (`--tag=forgepulse-config`)
- [ ] Migrations published (`--tag=forgepulse-migrations`)
- [ ] Migrations run locally (`php artisan migrate`)
- [ ] Package tested locally

---

## Configuration Changes

### File Updates

#### config/forgepulse.php
- [ ] Templates disk uses env fallback: `env('FORGEPULSE_TEMPLATE_DISK', env('FILESYSTEM_DISK', 's3'))`
- [ ] Queue connection uses env fallback: `env('FORGEPULSE_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sqs'))`
- [ ] Cache store uses env fallback: `env('FORGEPULSE_CACHE_STORE', env('CACHE_DRIVER', 'dynamodb'))`
- [ ] Log channel uses env fallback: `env('FORGEPULSE_LOG_CHANNEL', env('LOG_CHANNEL', 'stack'))`

#### config/filesystems.php
- [ ] S3 disk configured with AWS credentials
- [ ] Default disk uses env: `env('FILESYSTEM_DISK', 'local')`

#### config/queue.php
- [ ] SQS connection configured
- [ ] Default queue uses env: `env('QUEUE_CONNECTION', 'sync')`

#### config/cache.php
- [ ] DynamoDB store configured
- [ ] Default cache uses env: `env('CACHE_DRIVER', 'file')`

#### config/session.php
- [ ] Session driver changed to: `env('SESSION_DRIVER', 'database')`
- [ ] Sessions table migration created (`php artisan session:table`)
- [ ] Sessions migration run

### vapor.yml Creation
- [ ] File created in project root
- [ ] Project ID set
- [ ] Project name set
- [ ] Production environment configured:
  - [ ] Memory: 1024 MB
  - [ ] CLI Memory: 512 MB
  - [ ] Runtime: php-8.3
  - [ ] Timeout: 60 seconds
  - [ ] Database name set
  - [ ] Cache name set
  - [ ] Storage name set
  - [ ] Queue name set
- [ ] Build commands added
- [ ] Deploy commands added
- [ ] Staging environment configured (optional)

---

## Vapor Resources

### Database
- [ ] Database created (`vapor database forgepulse-db`)
- [ ] Database region set (e.g., us-east-1)
- [ ] Database credentials saved
- [ ] Database linked in vapor.yml

### Cache
- [ ] Cache created (`vapor cache forgepulse-cache`)
- [ ] Cache type: DynamoDB
- [ ] Cache linked in vapor.yml

### Storage
- [ ] Storage bucket created (`vapor storage forgepulse-storage`)
- [ ] Bucket region set
- [ ] Bucket linked in vapor.yml

### Queue
- [ ] Queue name specified in vapor.yml (auto-created on deploy)
- [ ] Queue workers configured

---

## Environment Variables

### Staging Environment
- [ ] Environment created in Vapor dashboard
- [ ] Variables set in dashboard or via CLI:

```bash
# Core Settings
APP_ENV=staging
APP_DEBUG=true
APP_URL=https://staging-url.vaporapp.io

# ForgePulse Settings
FORGEPULSE_TEMPLATE_DISK=s3
FORGEPULSE_QUEUE_CONNECTION=sqs
FORGEPULSE_CACHE_STORE=dynamodb
FORGEPULSE_LOG_CHANNEL=stderr
FORGEPULSE_ASYNC=true
FORGEPULSE_TIMEOUT=300

# Laravel Settings
QUEUE_CONNECTION=sqs
FILESYSTEM_DISK=s3
CACHE_DRIVER=dynamodb
SESSION_DRIVER=database
LOG_CHANNEL=stderr

# Database (Auto-set by Vapor)
DB_CONNECTION=mysql
DB_HOST=[VAPOR_PROVIDED]
DB_DATABASE=vapor
DB_USERNAME=vapor
DB_PASSWORD=[VAPOR_PROVIDED]

# AWS (Auto-set by Vapor)
AWS_BUCKET=[VAPOR_PROVIDED]
AWS_DEFAULT_REGION=us-east-1
SQS_PREFIX=[VAPOR_PROVIDED]
SQS_QUEUE=workflows
```

- [ ] Environment variables pushed (`vapor env:push staging`)

### Production Environment
- [ ] Environment created in Vapor dashboard
- [ ] Same variables as staging (with APP_DEBUG=false)
- [ ] Environment variables pushed (`vapor env:push production`)

---

## Asset Management

### Choose Strategy
- [ ] **Option A:** Vapor Auto-Sync (Recommended)
  - [ ] Assets published locally
  - [ ] Deploy command includes publish
  - [ ] Layout uses `asset()` helper
  
- [ ] **Option B:** Bundle with Vite
  - [ ] Assets copied to resources/css/vendor
  - [ ] Imported in app.css
  - [ ] Built with `npm run build`

---

## Staging Deployment

### Pre-Deployment
- [ ] All tests passing (`composer test`)
- [ ] Code linted (`composer analyse`)
- [ ] Assets built (`npm run build`)
- [ ] Git committed

### Deployment
- [ ] Deploy to staging (`vapor deploy staging`)
- [ ] Deployment successful (no errors)
- [ ] URL obtained (`vapor url staging`)

### Testing
- [ ] Website loads
- [ ] Assets load correctly (CSS/JS)
- [ ] Login works
- [ ] Livewire components render
- [ ] Create test workflow
- [ ] Execute test workflow
- [ ] Verify workflow completes
- [ ] Test template export
- [ ] Test template import
- [ ] Check queue processing (`vapor queue:monitor staging`)
- [ ] Review logs (`vapor logs staging --lines=50`)

### Verification
- [ ] No errors in CloudWatch logs
- [ ] Queue jobs processing
- [ ] S3 storage working
- [ ] DynamoDB cache working
- [ ] Database queries working

---

## Production Deployment

### Pre-Deployment
- [ ] Staging fully tested
- [ ] All issues resolved
- [ ] Team notified
- [ ] Maintenance window scheduled (if needed)
- [ ] Rollback plan prepared

### Deployment
- [ ] Deploy to production (`vapor deploy production`)
- [ ] Deployment successful
- [ ] URL obtained (`vapor url production`)
- [ ] DNS configured (if custom domain)

### Post-Deployment Testing
- [ ] Website loads
- [ ] Assets load correctly
- [ ] Login works
- [ ] Workflows execute
- [ ] Queue processing
- [ ] Logs clean
- [ ] No errors in CloudWatch

### Monitoring Setup
- [ ] CloudWatch alarms configured
- [ ] Slack/email notifications set up
- [ ] Queue metrics monitored
- [ ] Error tracking enabled
- [ ] Uptime monitoring configured

---

## Optimization

### Performance
- [ ] Lambda memory tuned (check metrics)
- [ ] Queue workers scaled appropriately
- [ ] Database indexed properly
- [ ] CloudFront caching verified
- [ ] Slow queries identified and optimized

### Cost
- [ ] Review Vapor billing dashboard
- [ ] Set up cost alerts
- [ ] Optimize unused resources
- [ ] Consider Reserved Capacity (if applicable)

### Cleanup
- [ ] Old logs cleaned up
- [ ] Unused templates removed
- [ ] Test data removed from production

---

## Documentation

### Internal Docs
- [ ] Deployment process documented
- [ ] Custom configurations documented
- [ ] Troubleshooting steps documented
- [ ] Team runbook created

### Knowledge Transfer
- [ ] Team trained on Vapor basics
- [ ] Team trained on ForgePulse
- [ ] On-call rotation set up
- [ ] Escalation procedures defined

---

## Maintenance

### Regular Tasks
- [ ] Weekly log review scheduled
- [ ] Monthly cost review scheduled
- [ ] Quarterly security audit scheduled
- [ ] Backup verification scheduled

### Updates
- [ ] ForgePulse update process defined
- [ ] Laravel update process defined
- [ ] Security patch procedure defined

---

## Emergency Procedures

### Rollback
- [ ] Rollback command tested: `vapor rollback production`
- [ ] Previous deployment IDs noted
- [ ] Rollback communication plan

### Incident Response
- [ ] Incident response plan documented
- [ ] Contact list updated
- [ ] Escalation path defined
- [ ] Post-mortem template prepared

---

## Sign-Off

### Staging
- [ ] **Developer:** Staging tested and approved
- [ ] **QA:** Staging testing complete
- [ ] **DevOps:** Infrastructure verified
- [ ] **Date:** _______________

### Production
- [ ] **Developer:** Production tested and approved
- [ ] **Team Lead:** Production deployment approved
- [ ] **Stakeholder:** Production go-live approved
- [ ] **Date:** _______________

---

## Post-Deployment

### Week 1
- [ ] Daily log reviews
- [ ] Daily cost reviews
- [ ] Daily performance checks
- [ ] No critical issues

### Week 2-4
- [ ] Every other day checks
- [ ] Weekly team sync
- [ ] Optimization opportunities identified

### Month 1
- [ ] Full month review
- [ ] Cost analysis complete
- [ ] Performance baseline established
- [ ] Documentation updated

---

## Success Criteria

- [ ] Workflows execute without errors
- [ ] Queue jobs process reliably
- [ ] Response times < 500ms (p95)
- [ ] Error rate < 0.1%
- [ ] Uptime > 99.9%
- [ ] Costs within budget
- [ ] Team comfortable with platform
- [ ] No outstanding critical issues

---

## Resources Used

- [ ] [VAPOR_README.md](VAPOR_README.md) - Overview
- [ ] [VAPOR_QUICK_START.md](VAPOR_QUICK_START.md) - Quick start
- [ ] [VAPOR_DEPLOYMENT_GUIDE.md](VAPOR_DEPLOYMENT_GUIDE.md) - Full guide
- [ ] [VAPOR_CONFIG_COMPARISON.md](VAPOR_CONFIG_COMPARISON.md) - Config reference
- [ ] [VAPOR_TROUBLESHOOTING.md](VAPOR_TROUBLESHOOTING.md) - Troubleshooting
- [ ] [Laravel Vapor Docs](https://docs.vapor.build)
- [ ] [ForgePulse Docs](https://alizharb.github.io/forgepulse/)

---

## Notes

Use this space for deployment-specific notes:

```
Date: _______________
Deployed by: _______________

Notes:
_________________________________________________
_________________________________________________
_________________________________________________
_________________________________________________
_________________________________________________
_________________________________________________
_________________________________________________
_________________________________________________
_________________________________________________
_________________________________________________
```

---

**Checklist Version:** 1.0
**Last Updated:** December 2025
**ForgePulse Version:** 1.2.0+
**Laravel Version:** 12+
