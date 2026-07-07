def pytest_runtest_logreport(report):
    """
    各テストの結果を表示する。
    """
    if report.when != "call":
        return

    #test_smoke.pyの場合、成功時にOKを出す
    if "tests/test_smoke.py::test_smoke[" in report.nodeid:
        case = report.nodeid.split("test_smoke[", 1)[1].rstrip("]")
        if report.passed:
            print(f"OK  : {case}")
        elif report.failed:
            print(f"FAIL: {case}")