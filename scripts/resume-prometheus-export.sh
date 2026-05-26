#!/bin/bash
cd /home/workspace/scarlet-overlay

# Get remaining metrics not yet in the export file
PROM_URL="http://44.30.69.5:9090"

REMAINING=$(python3 -c "
import json, urllib.request
data = json.loads(urllib.request.urlopen('${PROM_URL}/api/v1/label/__name__/values', timeout=10).read())['data']
done = {l.strip().split()[-2] for l in open('prometheus-export.om') if l.startswith('# TYPE ')}
remaining = [m for m in sorted(data) if m not in done]
print(' '.join(remaining))
")

echo "Resuming export for remaining metrics..."
python3 scripts/prometheus-export.py --url "$PROM_URL" --metrics $REMAINING --output prometheus-export-remaining.om 2>&1 | tee prometheus-export-resume.log

# Merge: strip EOF from remaining, append to main file
if [ -f prometheus-export-remaining.om ]; then
    grep -v '^# EOF$' prometheus-export-remaining.om >> prometheus-export.om
    echo '# EOF' >> prometheus-export.om
    rm prometheus-export-remaining.om
    echo "Merged into prometheus-export.om"
    wc -l prometheus-export.om
    ls -lh prometheus-export.om
fi
