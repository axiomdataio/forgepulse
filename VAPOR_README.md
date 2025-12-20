# ForgePulse + Laravel Vapor: Complete Guide Summary

**All documentation for deploying ForgePulse workflows on Laravel Vapor**

---

## 📚 Documentation Index

This repository contains comprehensive guides for running ForgePulse on Laravel Vapor:

| Document | Purpose | Audience |
|----------|---------|----------|
| **[VAPOR_QUICK_START.md](VAPOR_QUICK_START.md)** | 30-minute quick start guide | Developers (First-time setup) |
| **[VAPOR_DEPLOYMENT_GUIDE.md](VAPOR_DEPLOYMENT_GUIDE.md)** | Complete deployment guide | DevOps, Developers |
| **[VAPOR_CONFIG_COMPARISON.md](VAPOR_CONFIG_COMPARISON.md)** | Local vs Vapor configuration | Developers |
| **[VAPOR_TROUBLESHOOTING.md](VAPOR_TROUBLESHOOTING.md)** | Common issues and solutions | Support, Developers |

---

## 🚀 Quick Navigation

### First Time Setup?
👉 Start with **[VAPOR_QUICK_START.md](VAPOR_QUICK_START.md)**

### Need Full Details?
👉 Read **[VAPOR_DEPLOYMENT_GUIDE.md](VAPOR_DEPLOYMENT_GUIDE.md)**

### Something Not Working?
👉 Check **[VAPOR_TROUBLESHOOTING.md](VAPOR_TROUBLESHOOTING.md)**

### Comparing Configs?
👉 See **[VAPOR_CONFIG_COMPARISON.md](VAPOR_CONFIG_COMPARISON.md)**

---

## 🎯 Key Points

### What Works Out of the Box

✅ **Workflow Engine** - Core execution logic works perfectly
✅ **Livewire Components** - Visual workflow builder
✅ **Queue Jobs** - Using SQS
✅ **Database Operations** - RDS/Aurora
✅ **API Endpoints** - REST API for integrations
✅ **Events & Notifications** - All Laravel events
✅ **Versioning** - Automatic workflow versioning
✅ **Templates** - Export/import workflows

### What Needs Configuration

⚠️ **Template Storage** - Switch from `local` to `s3`
⚠️ **Queue Driver** - Switch from `sync` to `sqs`
⚠️ **Cache** - Switch from `file` to `dynamodb`
⚠️ **Sessions** - Switch from `file` to `database`
⚠️ **Assets** - Deploy via CloudFront

### What Doesn't Work on Vapor

❌ **PCNTL Timeouts** - Lambda doesn't support PCNTL extension
- **Impact:** Step-level timeouts won't work
- **Workaround:** Use job-level timeouts (already implemented)

⚠️ **Blocking sleep()** - Inefficient on Lambda
- **Impact:** Delay steps waste Lambda execution time
- **Workaround:** Use delayed queue jobs for long delays

---

## ⚡ Minimal Changes Required

### 1. Environment Variables

```bash
FORGEPULSE_TEMPLATE_DISK=s3
FORGEPULSE_QUEUE_CONNECTION=sqs
FORGEPULSE_CACHE_STORE=dynamodb
FORGEPULSE_LOG_CHANNEL=stderr
QUEUE_CONNECTION=sqs
FILESYSTEM_DISK=s3
SESSION_DRIVER=database
```

### 2. Configuration Updates

**`config/forgepulse.php`** - Add fallbacks:

```php
'templates' => [
    'disk' => env('FORGEPULSE_TEMPLATE_DISK', env('FILESYSTEM_DISK', 's3')),
],

'execution' => [
    'queue_connection' => env('FORGEPULSE_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sqs')),
],

'cache' => [
    'store' => env('FORGEPULSE_CACHE_STORE', env('CACHE_DRIVER', 'dynamodb')),
],
```

### 3. Create vapor.yml

```yaml
id: 1
name: your-app
environments:
  production:
    memory: 1024
    database: forgepulse-db
    cache: forgepulse-cache
    storage: forgepulse-storage
    queue: workflows
```

---

## 📊 Effort Estimate

| Task | Time | Complexity |
|------|------|------------|
| Install ForgePulse | 30 min | Low |
| Configure for Vapor | 1-2 hours | Low |
| Create Vapor resources | 1 hour | Low |
| Deploy & test | 2-3 hours | Medium |
| Troubleshoot issues | 1-2 hours | Medium |
| Production optimization | 2-3 hours | Medium |
| **TOTAL** | **7-11 hours** | **Medium** |

---

## 💰 Cost Estimate

### Monthly Vapor Costs (Moderate Usage)

| Resource | Cost (USD/month) |
|----------|-----------------|
| Lambda (compute) | $30-50 |
| RDS Database | $25-100 |
| S3 Storage | $1-5 |
| SQS Queue | $1-5 |
| DynamoDB Cache | $5-20 |
| CloudWatch Logs | $5-10 |
| Data Transfer | $5-10 |
| **TOTAL** | **$70-200/month** |

*Costs vary based on usage volume*

---

## 🔧 Configuration Checklist

### Prerequisites
- [ ] Laravel 12+ application
- [ ] PHP 8.3+
- [ ] Vapor account and CLI installed
- [ ] AWS account with billing enabled

### Package Installation
- [ ] Install ForgePulse via Composer
- [ ] Publish configuration
- [ ] Publish migrations
- [ ] Run migrations locally

### Vapor Setup
- [ ] Create `vapor.yml`
- [ ] Initialize Vapor project
- [ ] Create database (RDS)
- [ ] Create cache (DynamoDB)
- [ ] Create storage (S3)
- [ ] Configure queue (SQS)

### Configuration
- [ ] Update `config/forgepulse.php`
- [ ] Add S3 disk to `config/filesystems.php`
- [ ] Add SQS to `config/queue.php`
- [ ] Add DynamoDB to `config/cache.php`
- [ ] Change sessions to database
- [ ] Set environment variables in Vapor

### Assets
- [ ] Choose asset strategy (sync or bundle)
- [ ] Publish ForgePulse assets
- [ ] Update layout file
- [ ] Test asset loading

### Deployment
- [ ] Deploy to staging
- [ ] Test workflows on staging
- [ ] Deploy to production
- [ ] Verify production deployment

### Testing
- [ ] Create test workflow
- [ ] Execute workflow
- [ ] Test template export/import
- [ ] Test queue processing
- [ ] Verify assets load
- [ ] Check CloudWatch logs

### Monitoring
- [ ] Set up CloudWatch alarms
- [ ] Configure Slack/email notifications
- [ ] Monitor queue metrics
- [ ] Review costs in Vapor dashboard

### Optimization
- [ ] Tune Lambda memory
- [ ] Scale queue workers
- [ ] Enable caching
- [ ] Optimize database queries
- [ ] Clean up old logs

---

## 🎓 Learning Path

### For Developers New to Vapor

1. **Read Vapor Basics**
   - [Laravel Vapor Documentation](https://docs.vapor.build)
   - Understand serverless concepts
   - Learn about Lambda, RDS, S3, SQS

2. **Install ForgePulse Locally**
   - Get comfortable with workflow builder
   - Create test workflows
   - Understand step types

3. **Follow Quick Start**
   - Deploy to Vapor staging
   - Test basic functionality
   - Learn Vapor CLI commands

4. **Deep Dive**
   - Read full deployment guide
   - Understand configuration options
   - Learn troubleshooting techniques

5. **Production Deployment**
   - Deploy to production
   - Monitor and optimize
   - Document your setup

### For Experienced Vapor Users

1. **Install ForgePulse** (30 min)
2. **Update Config** (1 hour)
3. **Deploy** (30 min)
4. **Test** (1 hour)

You probably already know most of this!

---

## 🆘 Getting Help

### Documentation Issues
- **GitHub Issues:** https://github.com/alizharb/forgepulse/issues
- **Discussions:** https://github.com/alizharb/forgepulse/discussions

### Vapor-Specific Issues
- **Vapor Support:** support@vapor.build
- **Vapor Docs:** https://docs.vapor.build
- **Vapor Discord:** https://discord.gg/vapor

### Laravel Issues
- **Laravel Docs:** https://laravel.com/docs
- **Laracasts Forum:** https://laracasts.com/discuss
- **Laravel Discord:** https://discord.gg/laravel

---

## 📝 Best Practices

### Development Workflow

1. **Develop Locally**
   - Use local disk for templates
   - Use sync queue for quick testing
   - Enable debug mode

2. **Test on Staging**
   - Use Vapor staging environment
   - Test with production-like config
   - Verify queue processing

3. **Deploy to Production**
   - Use production configuration
   - Monitor closely after deployment
   - Have rollback plan ready

### Configuration Management

- Use `.env` files for different environments
- Never commit `.env.production` to git
- Use Vapor CLI for environment variables
- Document custom configuration

### Monitoring

- Set up CloudWatch alarms
- Monitor queue length
- Track error rates
- Review costs weekly

### Security

- Use IAM roles (Vapor handles this)
- Encrypt database at rest
- Use VPC for database
- Store secrets in AWS Secrets Manager
- Enable CloudTrail for audit logs

---

## 🔄 Update Strategy

### Updating ForgePulse

```bash
# Update package
composer update alizharb/forgepulse

# Publish new assets
php artisan vendor:publish --tag=forgepulse-assets --force

# Run new migrations
php artisan migrate

# Deploy to staging first
vapor deploy staging

# Test thoroughly
# ...

# Deploy to production
vapor deploy production
```

### Updating Laravel

Follow Laravel upgrade guide, then:

```bash
# Test locally
composer update
php artisan test

# Deploy to staging
vapor deploy staging

# Monitor for issues
vapor logs staging --follow

# Deploy to production
vapor deploy production
```

---

## 📈 Scaling Considerations

### When to Scale Up

**Lambda Memory:**
- Workflows timing out
- High memory usage in CloudWatch
- Complex workflow executions

**Queue Workers:**
- Long queue wait times
- Jobs piling up
- High traffic periods

**Database:**
- Connection pool exhausted
- Slow query performance
- High CPU/memory usage

**Cache:**
- High cache miss rate
- DynamoDB throttling
- Slow response times

### Scaling Strategies

**Horizontal Scaling:**
```yaml
# vapor.yml
environments:
  production:
    queues:
      - name: workflows
        workers: 10  # Increase workers
```

**Vertical Scaling:**
```yaml
environments:
  production:
    memory: 2048  # Increase memory
    cli-memory: 1024
```

**Database Scaling:**
```bash
# Use RDS Proxy
vapor database:proxy forgepulse-db

# Or scale instance
# Via Vapor dashboard: Databases > Scale
```

---

## 🎉 Success Criteria

Your ForgePulse + Vapor deployment is successful when:

- ✅ Workflows execute without errors
- ✅ Queue jobs process reliably
- ✅ Templates export/import to S3
- ✅ Assets load via CloudFront
- ✅ No timeout errors
- ✅ CloudWatch logs are clean
- ✅ Response times are acceptable
- ✅ Costs are within budget
- ✅ Monitoring is in place
- ✅ Team is trained

---

## 🚦 What's Next?

After successful deployment:

1. **Monitor Performance**
   - Review CloudWatch metrics daily
   - Check error rates
   - Monitor costs

2. **Optimize**
   - Tune Lambda memory
   - Scale queue workers as needed
   - Clean up old logs

3. **Document**
   - Document your custom steps
   - Create runbooks for common tasks
   - Train your team

4. **Expand**
   - Create more workflows
   - Build custom step handlers
   - Integrate with other services

5. **Contribute**
   - Report bugs
   - Suggest features
   - Share your experience

---

## 📚 Additional Resources

### Official Documentation
- [ForgePulse Docs](https://alizharb.github.io/forgepulse/)
- [Laravel Vapor Docs](https://docs.vapor.build)
- [Laravel Docs](https://laravel.com/docs)
- [AWS Lambda Docs](https://docs.aws.amazon.com/lambda/)

### Video Tutorials
- Laravel Vapor Introduction (YouTube)
- Serverless Laravel (Laracasts)
- AWS Lambda Best Practices

### Community
- [ForgePulse GitHub](https://github.com/alizharb/forgepulse)
- [Laravel Vapor Discord](https://discord.gg/vapor)
- [Laravel Discord](https://discord.gg/laravel)

### Tools
- [Vapor CLI](https://github.com/laravel/vapor-cli)
- [AWS CLI](https://aws.amazon.com/cli/)
- [Postman](https://www.postman.com/) - API testing

---

## ✨ Summary

**ForgePulse is 95% Vapor-ready out of the box.**

The main effort is in:
1. Configuring AWS resources (S3, SQS, DynamoDB)
2. Updating environment variables
3. Deploying assets properly

The package gracefully handles Vapor's limitations (PCNTL) and provides flexible configuration options.

**Total time to production: 7-11 hours for first deployment**

---

## 📞 Support

Need help? Here's how to get support:

1. **Check Documentation First**
   - Read relevant guide
   - Check troubleshooting guide
   - Search GitHub issues

2. **Gather Information**
   - Vapor logs
   - Configuration files
   - Steps to reproduce

3. **Ask for Help**
   - GitHub Issues (package issues)
   - Vapor Support (infrastructure issues)
   - Laravel Discord (general questions)

---

**Last Updated:** December 2025
**ForgePulse Version:** 1.2.0+
**Laravel Version:** 12+
**Vapor Runtime:** PHP 8.3

---

**Happy Deploying! 🚀**
